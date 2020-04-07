<?php

declare(strict_types=1);

namespace TextAtAnyCost;

/**
 * Read file from *.doc.
 * Microsoft Word Document extends the Windows Compound Binary File Format.
 *
 * @author Алексей Рембиш a.k.a Ramon <alex@rembish.ru> — original methods and algorythms
 * @author Andrew Zhdanovskih <andrew72ru@gmail.com> — OOP-style, refactoring and tests
 */
class Doc extends Cfb
{
    /**
     * {@inheritDoc}
     */
    public function parse(string $path = null): ?string
    {
        if ($path !== null) {
            $this->read($path);
        }

        // Wee need two streams: WordDocument and 0Table (or 1Table depends on situation).
        // First of all — find first stream, it contains the text parts for us.
        $wdStreamID = $this->getStreamIdByName('WordDocument');
        if ($wdStreamID === null) {
            return null;
        }
        $wdStream = $this->getStreamById($wdStreamID);

        $tStreamID = $this->getStreamIdByName($this->targetStreamName($wdStream));
        if ($tStreamID === false) {
            return null;
        }

        $tStream = $this->getStreamById($tStreamID);
        $clx = \substr($tStream, $this->getLong(0x01A2, $wdStream), $this->getLong(0x01A6, $wdStream));

        $pieceTable = $this->loadPieceTable($clx);

        $characterPositions = [];
        $i = 0;
        $lastCP = $this->lastCP($wdStream);
        while (($characterPositions[] = $this->getLong($i, $pieceTable)) !== $lastCP) {
            $i += 4;
        }
        // The rest of piece table is for Character descriptors
        $pieceDescriptors = \str_split(\substr($pieceTable, $i + 4), 8);

        $text = null;
        // The MAIN idea — read text from file
        // Walk for piece descriptors
        for ($i = 0, $iMax = \count($pieceDescriptors); $i < $iMax; ++$i) {
            // Get the word with offset and compression flag
            $fcValue = $this->getLong(2, $pieceDescriptors[$i]);
            // ANSI or Unicode
            $isANSI = ($fcValue & 0x40000000) === 0x40000000;
            // The rest (without head) goes to offset
            $fc = $fcValue & 0x3FFFFFFF;

            // Let the length of text part
            $lcb = $characterPositions[$i + 1] - $characterPositions[$i];
            // If we have a Unicode, we must read x2 files
            if (!$isANSI) {
                $lcb *= 2;
            } else {
                // If we have ANSI, start from half
                $fc /= 2;
            }

            // Get the depended of offset and size peace from WordDocument-stream
            $part = \substr($wdStream, $fc, $lcb);
            // If this is a Unicode, convert it to utf-8
            if (!$isANSI) {
                $part = $this->unicodeToUtf8($part);
            }

            // Add part to text
            $text .= $part;
        }

        // Remove entrances with objects
        $text = preg_replace('/HYPER13 *(INCLUDEPICTURE|HTMLCONTROL)(.*)HYPER15/iU', '', $text);
        $text = preg_replace('/HYPER13(.*)HYPER14(.*)HYPER15/iU', '$2', $text);

        return $text;
    }

    /**
     * @param string $wdStream
     *
     * @return string
     */
    private function targetStreamName(string $wdStream): string
    {
        $bytes = $this->getShort(0x000A, $wdStream);
        $fWhichTblStm = ($bytes & 0x0200) === 0x0200;

        return \sprintf('%dTable', (int) $fWhichTblStm);
    }

    /**
     * Original code authors comment:.
     *
     * > Отмечу, что здесь вааааааааааще жопа. В документации на сайте толком не сказано
     * > сколько гона может быть до pieceTable в этом CLX, поэтому будем исходить из тупого
     * > перебора - ищем возможное начало pieceTable (обязательно начинается на 0х02), затем
     * > читаем следующие 4 байта - размерность pieceTable. Если размерность по факту и
     * > размерность, записанная по смещению, то бинго! мы нашли нашу pieceTable. Нет?
     * > ищем дальше.
     *
     * @param string $clx
     *
     * @return string
     */
    private function loadPieceTable(string $clx): string
    {
        $pieceTable = '';
        $from = 0;
        while (($i = \strpos($clx, \chr(0x02), $from)) !== false) {
            $lcbPieceTable = $this->getLong($i + 1, $clx);
            $pieceTable = \substr($clx, $i + 5);
            if (\strlen($pieceTable) !== $lcbPieceTable) {
                $from = $i + 1;
                continue;
            }

            break;
        }

        return $pieceTable;
    }

    private function lastCP(string $wdStream): int
    {
        $arr = [0x0050, 0x0054, 0x0058, 0x005C, 0x0060, 0x0064, 0x0068];

        $ccpText = $this->getLong(0x004C, $wdStream);
        $lastCP = 0;
        foreach ($arr as $item) {
            $lastCP += $this->getLong($item, $wdStream);
        }

        $lastCP += ($lastCP !== 0) + $ccpText;

        return (int) $lastCP;
    }

    /**
     * {@inheritDoc}
     */
    protected function unicodeToUtf8($in, $check = false): ?string
    {
        $out = null;
        for ($i = 0, $iMax = \strlen($in); $i < $iMax; $i += 2) {
            $cd = \substr($in, $i, 2);

            if (\ord($cd[1]) === 0) {
                if (\ord($cd[0]) >= 32) {
                    $out .= $cd[0];
                } else {
                    $out .= ($val = $this->changeCommand(\ord($cd[0]))) !== null ? $val->current() : null;
                }
            } else {
                $out .= \html_entity_decode('&#x' . sprintf('%04x', $this->getShort(0, $cd)) . ';');
            }
        }

        return $out;
    }

    private function changeCommand(int $ord): ?\Generator
    {
        switch ($ord) {
            case 0x0D:
            case 0x07:
                yield "\n"; break;
            case 0x13:
                yield 'HYPER13'; break;
            case 0x14:
                yield 'HYPER14'; break;
            case 0x15:
                yield 'HYPER15'; break;
            case 0x08:
            case 0x01:
            default:
                break;
        }
    }
}
