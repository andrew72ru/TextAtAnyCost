<?php
/**
 * 30.03.2020
 */

declare(strict_types=1);


namespace TextAtAnyCost\ServiceClasses;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class CfbStorage
{
    /**
     * @var Collection Chain of MiniFAT-sectors
     */
    protected $miniFATChains;
    /**
     * @var Collection Chain of FAT-sectors
     */
    protected $fatChains;
    /**
     * @var Collection "Files" from FAT
     */
    protected $fatEntries;
    /**
     * @var bool Numbers writing way (little-endian)
     */
    private $isLittleEndian = true;
    /**
     * @var int
     */
    private $version = 3;
    /**
     * @var int FAT-sector shift (1 << 9 = 512)
     */
    private $sectorShift = 9;
    /**
     * @var int Mini-FAT sector shift (1 << 6 = 64)
     */
    private $miniSectorShift = 6;
    /**
     * @var int Maximum stream size in miniFAT
     */
    private $miniSectorCutoff = 4096;
    /**
     * @var int
     */
    private $fDir = 0;
    /**
     * @var int Count of "files"
     */
    private $cDir = 0;
    /**
     * @var int
     */
    private $cFAT = 0;
    /**
     * @var int
     */
    private $cMiniFAT = 0;
    /**
     * @var int
     */
    private $fMiniFAT = 0;
    /**
     * @var Collection DIFAT sectors
     */
    private $DIFAT;
    /**
     * @var int
     */
    private $cDIFAT = 0;
    /**
     * @var int
     */
    private $fDIFAT = 0;
    /**
     * @var string
     */
    private $miniFAT = '';

    public function __construct()
    {
        $this->miniFATChains = new ArrayCollection();
        $this->fatChains = new ArrayCollection();
        $this->fatEntries = new ArrayCollection();
        $this->DIFAT = new ArrayCollection();
    }

    /**
     * @return CfbStorage Factory method
     */
    public static function create(): self
    {
        return new static();
    }

    /**
     * @return Collection
     */
    public function getDIFAT(): Collection
    {
        return $this->DIFAT;
    }

    /**
     * @param array|Collection $DIFAT
     * @return CfbStorage
     */
    public function setDIFAT($DIFAT): self
    {
        $this->DIFAT = \is_array($DIFAT) ? new ArrayCollection($DIFAT) : $DIFAT;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getFatChains(): Collection
    {
        return $this->fatChains;
    }

    /**
     * @param array|Collection $fatChains
     * @return CfbStorage
     */
    public function setFatChains($fatChains): self
    {
        $this->fatChains = \is_array($fatChains) ? new ArrayCollection($fatChains) : $fatChains;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getFatEntries(): Collection
    {
        return $this->fatEntries;
    }

    /**
     * @param array|Collection $fatEntries
     * @return CfbStorage
     */
    public function setFatEntries($fatEntries): self
    {
        $this->fatEntries = \is_array($fatEntries) ? new ArrayCollection($fatEntries) : $fatEntries;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getMiniFATChains(): Collection
    {
        return $this->miniFATChains;
    }

    /**
     * @param array|Collection $miniFATChains
     * @return CfbStorage
     */
    public function setMiniFATChains($miniFATChains): self
    {
        $this->miniFATChains = \is_array($miniFATChains) ? new ArrayCollection($miniFATChains) : $miniFATChains;
        return $this;
    }

    /**
     * @return string
     */
    public function getMiniFAT(): string
    {
        return $this->miniFAT;
    }

    /**
     * @param string $miniFAT
     * @return CfbStorage
     */
    public function setMiniFAT(string $miniFAT): self
    {
        $this->miniFAT = $miniFAT;
        return $this;
    }

    /**
     * @return int
     */
    public function getCDir(): int
    {
        return $this->cDir;
    }

    /**
     * @param int $cDir
     * @return CfbStorage
     */
    public function setCDir(int $cDir): self
    {
        $this->cDir = $cDir;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLittleEndian(): bool
    {
        return $this->isLittleEndian;
    }

    /**
     * @param bool $isLittleEndian
     * @return CfbStorage
     */
    public function setIsLittleEndian(bool $isLittleEndian): self
    {
        $this->isLittleEndian = $isLittleEndian;
        return $this;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @param int $version
     * @return CfbStorage
     */
    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return int
     */
    public function getSectorShift(): int
    {
        return $this->sectorShift;
    }

    /**
     * @param int $sectorShift
     * @return CfbStorage
     */
    public function setSectorShift(int $sectorShift): self
    {
        $this->sectorShift = $sectorShift;
        return $this;
    }

    /**
     * @return int
     */
    public function getMiniSectorShift(): int
    {
        return $this->miniSectorShift;
    }

    /**
     * @param int $miniSectorShift
     * @return CfbStorage
     */
    public function setMiniSectorShift(int $miniSectorShift): self
    {
        $this->miniSectorShift = $miniSectorShift;
        return $this;
    }

    /**
     * @return int
     */
    public function getMiniSectorCutoff(): int
    {
        return $this->miniSectorCutoff;
    }

    /**
     * @param int $miniSectorCutoff
     * @return CfbStorage
     */
    public function setMiniSectorCutoff(int $miniSectorCutoff): self
    {
        $this->miniSectorCutoff = $miniSectorCutoff;
        return $this;
    }

    /**
     * @return int
     */
    public function getFDir(): int
    {
        return $this->fDir;
    }

    /**
     * @param int $fDir
     * @return CfbStorage
     */
    public function setFDir(int $fDir): self
    {
        $this->fDir = $fDir;
        return $this;
    }

    /**
     * @return int
     */
    public function getCFAT(): int
    {
        return $this->cFAT;
    }

    /**
     * @param int $cFAT
     * @return CfbStorage
     */
    public function setCFAT(int $cFAT): self
    {
        $this->cFAT = $cFAT;
        return $this;
    }

    /**
     * @return int
     */
    public function getCMiniFAT(): int
    {
        return $this->cMiniFAT;
    }

    /**
     * @param int $cMiniFAT
     * @return CfbStorage
     */
    public function setCMiniFAT(int $cMiniFAT): self
    {
        $this->cMiniFAT = $cMiniFAT;
        return $this;
    }

    /**
     * @return int
     */
    public function getFMiniFAT(): int
    {
        return $this->fMiniFAT;
    }

    /**
     * @param int $fMiniFAT
     * @return CfbStorage
     */
    public function setFMiniFAT(int $fMiniFAT): self
    {
        $this->fMiniFAT = $fMiniFAT;
        return $this;
    }

    /**
     * @return int
     */
    public function getCDIFAT(): int
    {
        return $this->cDIFAT;
    }

    /**
     * @param int $cDIFAT
     * @return CfbStorage
     */
    public function setCDIFAT(int $cDIFAT): self
    {
        $this->cDIFAT = $cDIFAT;
        return $this;
    }

    /**
     * @return int
     */
    public function getFDIFAT(): int
    {
        return $this->fDIFAT;
    }

    /**
     * @param int $fDIFAT
     * @return CfbStorage
     */
    public function setFDIFAT(int $fDIFAT): self
    {
        $this->fDIFAT = $fDIFAT;
        return $this;
    }
}
