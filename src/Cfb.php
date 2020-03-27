<?php

// Чтение WCBFF
// Версия 0.2
// Автор: Алексей Рембиш a.k.a Ramon
// E-mail: alex@rembish.ru
// Copyright 2009

// Итак, мальчики-девочки, перед вами класс для работы с WCBFF, что расшифровывается, как
// Windows Compound Binary File Format. Зачем это нужно? На основе этого
// формата строятся такие "вкусные" файлы как .doc, .xls и .ppt. Поехали, смотреть, как
// это устроено!

namespace TextAtAnyCost;

use RuntimeException;

abstract class Cfb implements ConverterInterface, ReadFileInterface
{
    /**
     * @var string|null File contents as string
     */
    protected $data;

    /**
     * @var int FAT-sector shift (1 << 9 = 512)
     */
    protected $sectorShift = 9;

    /**
     * @var int Mini-FAT sector shift (1 << 6 = 64)
     */
    protected $miniSectorShift = 6;

    /**
     * @var int Maximum stream size in miniFAT
     */
    protected $miniSectorCutoff = 4096;

    /**
     * @var array Chain of FAT-sectors
     */
    protected $fatChains = [];

    /**
     * @var array "Files" from FAT
     */
    protected $fatEntries = [];

    /**
     * @var array Chain of MiniFAT-sectors
     */
    protected $miniFATChains = [];

    /**
     * @var string whole MiniFat
     */
    protected $miniFAT = '';

    /**
     * @var int may be 3 or 4
     */
    private $version = 3;

    /**
     * @var bool Numbers writing way (little-endian)
     */
    private $isLittleEndian = true;

    /**
     * @var int Count of "files"
     */
    private $cDir = 0;

    /**
     * @var int Description position of first "file" in FAT
     */
    private $fDir = 0;

    // Количество FAT-секторов в файле
    /**
     * @var int count of FAT-sectors in "file"
     */
    private $cFAT = 0;

    /**
     * @var int Count of MiniFAT sectors
     */
    private $cMiniFAT = 0;

    /**
     * @var int Position of MiniFAT-sectors sequence in file
     */
    private $fMiniFAT = 0;

    /**
     * @var array DIFAT sectors
     */
    private $DIFAT = [];

    /**
     * @var int Shift of DIFAT
     */
    private $cDIFAT = 0;
    private $fDIFAT = 0;

    protected const END_OF_CHAIN = 0xFFFFFFFE;
    protected const FREE_SECT = 0xFFFFFFFF;

    public const HEADERS = ['D0CF11E0A1B11AE1', '0E11FC0DD0CF11E0'];

    /**
     * @param string $filename
     */
    public function read(string $filename): void
    {
        if (!\is_file($filename) || !\is_readable($filename)) {
            throw new RuntimeException(\sprintf('Unable to read file \'%s\'', $filename));
        }

        $this->data = \file_get_contents($filename);
        $this->checkFile();
    }

    /**
     * Check the data is actually CFB structure, assign structure parts to inner properties.
     */
    protected function checkFile(): void
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
        $this->miniFAT = $this->getStreamById($reStreamID, true);
        unset($this->DIFAT);
    }

    abstract public function parse(): ?string;

    /**
     * Find the stream number in "directory" structure by name.
     *
     * @param $name
     * @param int $from
     * @return null|int
     */
    protected function getStreamIdByName($name, $from = 0): ?int
    {
        for ($i = $from, $iMax = count($this->fatEntries); $i < $iMax; ++$i) {
            if ($this->fatEntries[$i]['name'] === $name) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param int $id Stream number
     * @param bool $isRoot
     * @return null|string binary string of stream
     */
    protected function getStreamById(int $id, bool $isRoot = false): ?string
    {
        $entry = $this->fatEntries[$id];
        // TODO check the keys
        $from = $entry['start'];
        $size = $entry['size'];

        // Two options here: if size less than 4096 bytes, read data from MiniFAT, if larger — from main FAT.
        // Except RootEntry — we should read FAT from RootEntry, it store the MiniFAT
        $stream = null;
        // Here is a first option — small size and not RootEntry
        if ($size < $this->miniSectorCutoff && !$isRoot) {
            // get the miniFAT sector size — 64 bytes
            $sSize = 1 << $this->miniSectorShift;
            $this->readToStream($stream,$from, $this->miniSectorShift, $sSize, $this->miniFAT, $this->miniFATChains);
        } else {
            // Second option (big part) — read from FAT
            $sSize = 1 << $this->sectorShift;
            $this->readToStream($stream, $from, $this->sectorShift, $sSize, $this->data, $this->fatChains);
        }
        // Stream content with care of size
        return ($result = \substr($stream, 0, $size)) === false ? null : $result;
    }

    /**
     * @param string|null $stream
     * @param mixed       $from
     * @param int         $shift
     * @param int         $stopShift
     * @param string      $source
     * @param array       $chains
     */
    private function readToStream(?string &$stream, &$from, int $shift, int $stopShift, string $source, array $chains): void
    {
        $start = $from << $shift;
        $part = \substr($source, $start, $stopShift);
        if ($part) {
            $stream .= $part;
        }
        $from = $chains[$from] ?? self::END_OF_CHAIN;
        if ($from !== self::END_OF_CHAIN) {
            $this->readToStream($stream, $from, $shift, $stopShift, $source, $chains);
        }
    }

    /**
     * Read important data from file header
     */
    private function readHeader(): void
    {
        // First of all, we should now how exactly data stored in file
        $uByteOrder = \strtoupper(\bin2hex(\substr($this->data, 0x1C, 2)));
        // We are suppose that is "little-endian" record, but check it for sure
        $this->isLittleEndian = $uByteOrder === 'FEFF';

        // Check the version
        $this->version = $this->getShort(0x1A);

        // Offsets for FAT and miniFAT
        $this->sectorShift = $this->getShort(0x1E);
        $this->miniSectorShift = $this->getShort(0x20);
        $this->miniSectorCutoff = $this->getLong(0x38);

        // Count of occurrences to file directory and offsets to first description in file
        if ($this->version === 4) {
            $this->cDir = $this->getLong(0x28);
        }
        $this->fDir = $this->getLong(0x30);

        // Count of FAT-sectors in file
        $this->cFAT = $this->getLong(0x2C);

        // Count and position of first MiniFat-sector of chains
        $this->cMiniFAT = $this->getLong(0x40);
        $this->fMiniFAT = $this->getLong(0x3C);

        // Where FAT-sectors chains is and count of it
        $this->cDIFAT = $this->getLong(0x48);
        $this->fDIFAT = $this->getLong(0x44);
    }

    /**
     * DIFAT shows the sectors with FAT-sectors descriptions.
     */
    private function readDIFAT(): void
    {
        $this->DIFAT = [];
        // First 109 links for chains in header
        for ($i = 0; $i < 109; ++$i) {
            $this->DIFAT[$i] = $this->getLong(0x4C + $i * 4);
        }

        // If file larger than 8.5Mb, it contains another links for chains
        if ($this->fDIFAT !== self::END_OF_CHAIN) {
            $size = 1 << $this->sectorShift;
            $from = $this->fDIFAT;
            $j = 0;

            do {
                $start = ($from + 1) << $this->sectorShift;
                for ($i = 0; $i < ($size - 4); $i += 4) {
                    $this->DIFAT[] = $this->getLong($start + $i);
                }
                $from = $this->getLong($start + $i);
            } while ($from !== self::END_OF_CHAIN && ++$j < $this->cDIFAT);
        }

        while ($this->DIFAT[count($this->DIFAT) - 1] === self::FREE_SECT) {
            \array_pop($this->DIFAT);
        }
    }

    /**
     * Convert the links to FAT-sectors chains ro real chains.
     */
    private function readFATChains(): void
    {
        // Sector size
        $size = 1 << $this->sectorShift;
        $this->fatChains = [];

        foreach ($this->DIFAT as $iValue) {
            $from = ($iValue + 1) << $this->sectorShift;
            // Take the FAT-chain: array index is a current sector, array value is a next element or END_OF_CHAIN if this is a last element
            for ($j = 0; $j < $size; $j += 4) {
                $this->fatChains[] = $this->getLong($from + $j);
            }
        }
    }

    /**
     * Read the MiniFAT-chains
     */
    private function readMiniFATChains(): void
    {
        // Sector size
        $size = 1 << $this->sectorShift;
        $this->miniFATChains = [];

        $from = $this->fMiniFAT;
        while ($from !== self::END_OF_CHAIN) {
            $start = ($from + 1) << $this->sectorShift;
            for ($i = 0; $i < $size; $i += 4) {
                $this->miniFATChains[] = $this->getLong($start + $i);
            }
            $from = $this->fatChains[$from] ?? self::END_OF_CHAIN;
        }
    }

    /**
     * Read the "directory structure" of file.
     * This structure contains all "Filesystem-objects" of this file.
     */
    private function readDirectoryStructure(): void
    {
        // First sector with "files"
        $from = $this->fDir;
        // Get the sector size
        $size = 1 << $this->sectorShift;
        $this->fatEntries = [];
        do {
            // Lookup for sector in file
            $start = ($from + 1) << $this->sectorShift;
            // Walk by sector contents.
            // One sector may contains up to 4 (128 for fourth version) occurrences to Filesystem. Read it.
            for ($i = 0; $i < $size; $i += 128) {
                // The binary data part
                $entry = substr($this->data, $start + $i, 128);
                // work with it
                $this->fatEntries[] = [
                    // Get the occurrence name
                    'name' => $this->utf16ToAnsi(\substr($entry, 0, $this->getShort(0x40, $entry))),
                    // Get type of occurrence — user data, empty sector etc.
                    'type' => ord($entry[0x42]),
                    // "color" in Red-Black tree
                    'color' => ord($entry[0x43]),
                    // it "left" siblings
                    'left' => $this->getLong(0x44, $entry),
                    // it "right" siblings
                    'right' => $this->getLong(0x48, $entry),
                    // it child element
                    'child' => $this->getLong(0x4C, $entry),
                    // shift for contents in FAT or miniFAT
                    'start' => $this->getLong(0x74, $entry),
                    // Contents size
                    'size' => $this->getSomeBytes($entry, 0x78, 8),
                ];
            }

            // Find the next sector with description and go to it
            $from = $this->fatChains[$from] ?? self::END_OF_CHAIN;
            // (if it exists)
        } while ($from !== self::END_OF_CHAIN);

        // Remove empty occurrences if they are exists
        // TODO refactor
        while ($this->fatEntries[count($this->fatEntries) - 1]['type'] === 0) {
            \array_pop($this->fatEntries);
        }
    }

    /**
     * Helper function for receive name of current occurrence to FS.
     * Note that names stored in Unicode
     *
     * @param string $in
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
     * Convert Unicode to UTF8
     *
     * @param string $in
     * @return string
     */
    protected function unicodeToUtf8(string $in): string
    {
        return \html_entity_decode(\mb_convert_encoding($in, 'UTF-8'), \ENT_QUOTES, 'UTF-8');
    }

    /**
     * Helper function for reading some bytes from string
     * with attention to bytes order and convert value to number.
     *
     * @param string|null $data
     * @param int $from
     * @param int $count
     * @return float
     */
    protected function getSomeBytes(?string $data, int $from, int $count): float
    {
        if ($data === null) {
            $data = $this->data;
        }

        $string = \substr($data, $from, $count);
        if ($this->isLittleEndian) {
            $string = \strrev($string);
        }

        return (float) \hexdec(\bin2hex($string));
    }

    // Читаем слово из переменной (по умолчанию из this->data)

    /**
     * Read the "word" from string
     *
     * @param int $from
     * @param string|null $data
     * @return float
     */
    protected function getShort(int $from, ?string $data = null): float
    {
        return $this->getSomeBytes($data, $from, 2);
    }

    /**
     * Read the "double word" from string.
     *
     * @param int $from
     * @param string|null $data
     * @return float
     */
    protected function getLong(int $from, ?string $data = null): float
    {
        return $this->getSomeBytes($data, $from, 4);
    }
}
