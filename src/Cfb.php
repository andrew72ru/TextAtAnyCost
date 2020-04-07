<?php

namespace TextAtAnyCost;

use TextAtAnyCost\ServiceClasses\{CfbStorage, FatEntry};

/**
 * Main class Cfb.
 * This is a class for work with WCBFF (Windows Compound Binary File Format).
 * The .doc, .xls and .ppt files are based on this formats.
 *
 * @author Алексей Рембиш a.k.a Ramon <alex@rembish.ru> — original methods and algorythms
 * @author Andrew Zhdanovskih <andrew72ru@gmail.com> — OOP-style, refactoring and tests
 *
 * @see https://github.com/rembish/TextAtAnyCost — the original package.
 */
abstract class Cfb implements ConverterInterface, ReadFileInterface
{
    /**
     * @var string|null File contents as string
     */
    protected $data;

    protected const END_OF_CHAIN = 0xFFFFFFFE;
    protected const FREE_SECT = 0xFFFFFFFF;

    public const HEADERS = ['D0CF11E0A1B11AE1', '0E11FC0DD0CF11E0'];

    /**
     * @var CfbStorage
     */
    private $storage;

    /**
     * Cfb constructor.
     *
     * @param CfbStorage|null $storage
     */
    public function __construct(CfbStorage $storage = null)
    {
        if ($storage === null) {
            $this->storage = CfbStorage::create();
        } else {
            $this->storage = $storage;
        }
    }

    /**
     * @return CfbStorage
     */
    public function getStorage(): CfbStorage
    {
        return $this->storage;
    }

    /**
     * @param string $filename
     */
    public function read(string $filename): void
    {
        if (!\is_file($filename) || !\is_readable($filename)) {
            throw new \RuntimeException(\sprintf('Unable to read file \'%s\'', $filename));
        }

        $this->data = \file_get_contents($filename) ?: null;
        $this->loadVariables();
    }

    /**
     * Check the data is actually CFB structure, assign structure parts to inner properties.
     */
    protected function loadVariables(): void
    {
        $abSig = \strtoupper(\bin2hex(\substr($this->data, 0, 8)));
        if (!\in_array($abSig, self::HEADERS, true)) {
            throw new \RuntimeException('Data is not CFB structure');
        }

        $this->readHeader();
        $this->readDIFAT();
        $this->readFATChains();
        $this->readMiniFATChains();
        $this->readDirectoryStructure();

        $reStreamID = $this->getStreamIdByName('Root Entry');
        if ($reStreamID === null) {
            throw new \RuntimeException('Cannot find root entry');
        }
        $this->storage->setMiniFAT($this->getStreamById($reStreamID, true));
    }

    /**
     * @return string|null
     */
    abstract public function parse(): ?string;

    /**
     * Convert binary unicode string to utf-8 string.
     *
     * @param string $in
     * @param bool   $check
     *
     * @return string|null
     */
    abstract protected function unicodeToUtf8($in, $check = false): ?string;

    /**
     * Find the stream number in "directory" structure by name.
     *
     * @param $name
     * @param int $from
     *
     * @return int|null
     */
    protected function getStreamIdByName($name, $from = 0): ?int
    {
        for ($i = $from, $iMax = $this->storage->getFatEntries()->count(); $i < $iMax; ++$i) {
            $entry = $this->storage->getFatEntries()->get($i);
            if ($entry instanceof FatEntry && $entry->getName() === $name) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param int  $id     Stream number
     * @param bool $isRoot
     *
     * @return string|null binary string of stream
     */
    protected function getStreamById(int $id, bool $isRoot = false): ?string
    {
        $entry = $this->storage->getFatEntries()->get($id);
        if (!$entry instanceof FatEntry) {
            return null;
        }

        $from = $entry->getStart();
        $size = $entry->getSize();

        // Two options here: if size less than 4096 bytes, read data from MiniFAT, if larger — from main FAT.
        // Except RootEntry — we should read FAT from RootEntry, it store the MiniFAT
        $stream = null;

        // Here is a first option — small size and not RootEntry
        if (!$isRoot && $size < $this->storage->getMiniSectorCutoff()) {
            // get the miniFAT sector size — 64 bytes
            $sSize = 1 << $this->storage->getMiniSectorShift();

            do {
                $start = $from << $this->storage->getMiniSectorShift();
                $stream .= \substr($this->storage->getMiniFAT(), $start, $sSize);
                $from = $this->storage->getMiniFATChains()->get($from) ?? self::END_OF_CHAIN;
            } while ($from !== self::END_OF_CHAIN);
        } else {
            $sSize = 1 << $this->storage->getSectorShift();

            do {
                $start = ($from + 1) << $this->storage->getSectorShift();
                $stream .= substr($this->data, $start, $sSize);
                $from = $this->storage->getFatChains()->get($from) ?? self::END_OF_CHAIN;
            } while ($from !== self::END_OF_CHAIN);
        }
        // Stream content with care of size
        return ($result = \substr($stream, 0, $size)) === false ? null : $result;
    }

    /**
     * Read important data from file header.
     */
    private function readHeader(): void
    {
        // First of all, we should now how exactly data stored in file
        $uByteOrder = \strtoupper(\bin2hex(\substr($this->data, 0x1C, 2)));
        // We are suppose that is "little-endian" record, but check it for sure
        $this->storage->setIsLittleEndian($uByteOrder === 'FEFF');

        // Check the version
        $this->storage->setVersion($this->getShort(0x1A));

        // Offsets for FAT and miniFAT
        $this->storage->setSectorShift($this->getShort(0x1E));
        $this->storage->setMiniSectorShift($this->getShort(0x20));
        $this->storage->setMiniSectorCutoff($this->getLong(0x38));

        // Count of occurrences to file directory and offsets to first description in file
        if ($this->storage->getVersion() === 4) {
            $this->storage->setCDir($this->getLong(0x28));
        }
        $this->storage->setFDir($this->getLong(0x30));

        // Count of FAT-sectors in file
        $this->storage->setCFAT($this->getLong(0x2C));

        // Count and position of first MiniFat-sector of chains
        $this->storage->setFMiniFAT($this->getLong(0x3C));

        // Where FAT-sectors chains is and count of it
        $this->storage->setCDIFAT($this->getLong(0x48));
        $this->storage->setFDIFAT($this->getLong(0x44));
    }

    /**
     * DIFAT shows the sectors with FAT-sectors descriptions.
     */
    private function readDIFAT(): void
    {
        // First 109 links for chains in header
        for ($i = 0; $i < 109; ++$i) {
            $this->storage->getDIFAT()->set($i, $this->getLong(0x4C + $i * 4));
        }

        // If file larger than 8.5Mb, it contains another links for chains
        if ($this->storage->getFDIFAT() !== self::END_OF_CHAIN) {
            $size = 1 << $this->storage->getSectorShift();
            $from = $this->storage->getFDIFAT();
            $j = 0;

            do {
                $start = ($from + 1) << $this->storage->getSectorShift();
                for ($i = 0; $i < ($size - 4); $i += 4) {
                    $this->storage->getDIFAT()->add($this->getLong($start + $i));
                }
                $from = $this->getLong($start + $i);
            } while ($from !== self::END_OF_CHAIN && ++$j < $this->storage->getCDIFAT());
        }
        foreach ($this->storage->getDIFAT() as $item) {
            if ($item === self::FREE_SECT) {
                $this->storage->getDIFAT()->removeElement($item);
            }
        }
    }

    /**
     * Convert the links to FAT-sectors chains ro real chains.
     */
    private function readFATChains(): void
    {
        // Sector size
        $size = 1 << $this->storage->getSectorShift();

        foreach ($this->storage->getDIFAT() as $iValue) {
            $from = ($iValue + 1) << $this->storage->getSectorShift();
            // Take the FAT-chain: array index is a current sector, array value is a next element or END_OF_CHAIN if this is a last element
            for ($j = 0; $j < $size; $j += 4) {
                $this->storage->getFatChains()->add($this->getLong($from + $j));
            }
        }
    }

    /**
     * Read the MiniFAT-chains.
     */
    private function readMiniFATChains(): void
    {
        // Sector size
        $size = 1 << $this->storage->getSectorShift();

        $from = $this->storage->getFMiniFAT();
        while ($from !== self::END_OF_CHAIN) {
            $start = ($from + 1) << $this->storage->getSectorShift();
            for ($i = 0; $i < $size; $i += 4) {
                $this->storage->getMiniFATChains()->add($this->getLong($start + $i));
            }
            $from = $this->storage->getFatChains()->get($from) ?? self::END_OF_CHAIN;
        }
    }

    /**
     * Read the "directory structure" of file.
     * This structure contains all "Filesystem-objects" of this file.
     */
    private function readDirectoryStructure(): void
    {
        // First sector with "files"
        $from = $this->storage->getFDir();
        // Get the sector size
        $size = 1 << $this->storage->getSectorShift();

        do {
            // Lookup for sector in file
            $start = ($from + 1) << $this->storage->getSectorShift();
            // Walk by sector contents.
            // One sector may contains up to 4 (128 for fourth version) occurrences to Filesystem. Read it.
            for ($i = 0; $i < $size; $i += 128) {
                // The binary data part
                $entry = \substr($this->data, $start + $i, 128);
                $name = $this->utf16ToAnsi(\substr($entry, 0, $this->getShort(0x40, $entry)));
                if ($name === null) {
                    continue;
                }

                $fatEntry = (new FatEntry())
                    ->setName($name)
                    ->setType(\ord($entry[0x42]))
                    ->setColor(\ord($entry[0x43]))
                    ->setLeft($this->getLong(0x44, $entry))
                    ->setRight($this->getLong(0x48, $entry))
                    ->setChild($this->getLong(0x4C, $entry))
                    ->setStart($this->getLong(0x74, $entry))
                    ->setSize($this->getSomeBytes($entry, 0x78, 8))
                ;

                $this->storage->getFatEntries()->add($fatEntry);
            }

            $from = $this->storage->getFatChains()->get($from) ?? self::END_OF_CHAIN;
        } while ($from !== self::END_OF_CHAIN);

        // Remove empty occurrences if they are exists
        foreach ($this->storage->getFatEntries() as $fatEntry) {
            if ($fatEntry->getType() === 0) {
                $this->storage->getFatEntries()->removeElement($fatEntry);
            }
        }
    }

    /**
     * Helper function for receive name of current occurrence to FS.
     * Note that names stored in Unicode.
     *
     * @param string $in
     *
     * @return string|null
     */
    private function utf16ToAnsi(string $in): ?string
    {
        $out = null;
        for ($i = 0, $iMax = \strlen($in); $i < $iMax; $i += 2) {
            $out .= \chr($this->getShort($i, $in));
        }

        return \trim($out);
    }

    /**
     * Helper function for reading some bytes from string
     * with attention to bytes order and convert value to number.
     *
     * @param string|null $data
     * @param int         $from
     * @param int         $count
     *
     * @return int
     */
    protected function getSomeBytes(?string $data, int $from, int $count): int
    {
        $string = \substr($data ?? $this->data, $from, $count);
        if ($string === false) {
            return 0;
        }

        if ($this->storage->isLittleEndian()) {
            $string = \strrev($string);
        }

        return (int) \hexdec(\bin2hex($string));
    }

    /**
     * Read the "word" from string.
     *
     * @param int         $from
     * @param string|null $data
     *
     * @return int
     */
    protected function getShort(int $from, ?string $data = null): int
    {
        return $this->getSomeBytes($data ?? $this->data, $from, 2);
    }

    /**
     * Read the "double word" from string.
     *
     * @param int         $from
     * @param string|null $data
     *
     * @return int
     */
    protected function getLong(int $from, ?string $data = null): int
    {
        return $this->getSomeBytes($data ?? $this->data, $from, 4);
    }
}
