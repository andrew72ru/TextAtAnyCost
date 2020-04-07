<?php
/**
 * 30.03.2020.
 */

declare(strict_types=1);

namespace TextAtAnyCost\ServiceClasses;

class FatEntry
{
    /**
     * @var string occurrence name
     */
    private $name = '';

    /**
     * @var int type of occurrence — user data, empty sector etc
     */
    private $type = 0;

    /**
     * @var int "color" in Red-Black tree
     */
    private $color = 0;

    /**
     * @var int "left" siblings
     */
    private $left = 0;

    /**
     * @var int "right" siblings
     */
    private $right = 0;

    /**
     * @var int child element
     */
    private $child = 0;

    /**
     * @var int shift for contents in FAT or miniFAT
     */
    private $start = 0;

    /**
     * @var int Contents size
     */
    private $size = 0;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return FatEntry
     */
    public function setName(string $name): FatEntry
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return FatEntry
     */
    public function setType(int $type): FatEntry
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return int
     */
    public function getColor(): int
    {
        return $this->color;
    }

    /**
     * @param int $color
     *
     * @return FatEntry
     */
    public function setColor(int $color): FatEntry
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return int
     */
    public function getLeft(): int
    {
        return $this->left;
    }

    /**
     * @param int $left
     *
     * @return FatEntry
     */
    public function setLeft(int $left): FatEntry
    {
        $this->left = $left;

        return $this;
    }

    /**
     * @return int
     */
    public function getRight(): int
    {
        return $this->right;
    }

    /**
     * @param int $right
     *
     * @return FatEntry
     */
    public function setRight(int $right): FatEntry
    {
        $this->right = $right;

        return $this;
    }

    /**
     * @return int
     */
    public function getChild(): int
    {
        return $this->child;
    }

    /**
     * @param int $child
     *
     * @return FatEntry
     */
    public function setChild(int $child): FatEntry
    {
        $this->child = $child;

        return $this;
    }

    /**
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * @param int $start
     *
     * @return FatEntry
     */
    public function setStart(int $start): FatEntry
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param int $size
     *
     * @return FatEntry
     */
    public function setSize(int $size): FatEntry
    {
        $this->size = $size;

        return $this;
    }
}
