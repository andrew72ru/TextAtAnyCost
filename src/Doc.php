<?php declare(strict_types=1);

namespace TextAtAnyCost;

final class Doc extends Cfb
{
    public function parse(): ?string
    {
        // Wee need two streams: WordDocument and 0Table (or 1Table depends on situation).
        // First of all — find first stream, it contains the text parts for us.
        $wdStreamID = $this->getStreamIdByName('WordDocument');
        if ($wdStreamID === null) {
            return null;
        }

        // We have found the first stream
        $wdStream = $this->getStreamById($wdStreamID);

        // We need to get the special block with name 'File Information Block' in start of WordDocument stream.
        $bytes = $this->getShort(0x000A, $wdStream);
        // This bit shows which table are we need — first or zero
        $fWhichTblStm = ($bytes & 0x0200) === 0x0200;

        // We should know the CLX position and size
        $fcClx = $this->getLong(0x01A2, $wdStream);
        $lcbClx = $this->getLong(0x01A6, $wdStream);

        // Find a needing table in file
        $tStreamID = $this->getStreamIdByName((int) $fWhichTblStm . 'Table');
        if ($tStreamID === null) {
            return null;
        }

        // Read the stream to variable
        $tStream = $this->getStreamById($tStreamID);
        // Find CLX in stream
        $clx = \substr($tStream, $fcClx, $lcbClx);
        $pieceTable = $this->findPeaceTable($clx);

        // Теперь заполняем массив character positions, пока не наткнћмся
        // на последний CP.
        $lastCP = $this->getLastCharacterPosition($wdStream);
        $cp = [];
        $i = 0;
        while (($cp[] = $this->getLong($i, $pieceTable)) !== $lastCP) {
            $i += 4;
        }
        // Остаток идћт на PCD (piece descriptors)
        $pcd = \str_split(\substr($pieceTable, $i + 4), 8);

        $text = null;
        // Ура! мы подошли к главному - чтение текста из файла.
        // Идћм по декскрипторам кусочков
        for ($i = 0, $iMax = \count($pcd); $i < $iMax; ++$i) {
            // Получаем слово со смещением и флагом компрессии
            $fcValue = $this->getLong(2, $pcd[$i]);
            // Смотрим - что перед нами тупой ANSI или Unicode
            $isANSI = ($fcValue & 0x40000000) === 0x40000000;
            // Остальное без макушки идћт на смещение
            $fc = $fcValue & 0x3FFFFFFF;

            // Получаем длину кусочка текста
            $lcb = $cp[$i + 1] - $cp[$i];
            // Если перед нами Unicode, то мы должны прочитать в два раза больше файлов
            if (!$isANSI) {
                $lcb *= 2;
            } // Если ANSI, то начать в два раза раньше.
            else {
                $fc /= 2;
            }

            // Читаем кусок с учћтом смещения и размера из WordDocument-потока
            $part = \substr($wdStream, $fc, $lcb);
            // Если перед нами Unicode, то преобразовываем его в нормальное состояние
            if (!$isANSI) {
                $part = $this->unicodeToUtf8($part);
            }

            // Добавляем кусочек к общему тексту
            $text .= $part;
        }

        // Удаляем из файла вхождения с внедрћнными объектами
        $text = \preg_replace('/HYPER13 *(INCLUDEPICTURE|HTMLCONTROL)(.*)HYPER15/iU', '', $text);
        $text = \preg_replace('/HYPER13(.*)HYPER14(.*)HYPER15/iU', '$2', $text);
        // Возвращаем результат

        return $text;
    }

    /**
     * @param string $clx
     * @return string
     */
    protected function findPeaceTable(string $clx): string
    {
        // Отмечу, что здесь вааааааааааще жопа. В документации на сайте толком не сказано
        // сколько гона может быть до pieceTable в этом CLX, поэтому будем исходить из тупого
        // перебора - ищем возможное начало pieceTable (обязательно начинается на 0х02), затем
        // читаем следующие 4 байта - размерность pieceTable. Если размерность по факту и
        // размерность, записанная по смещению, то бинго! мы нашли нашу pieceTable. Нет?
        // ищем дальше.

        $pieceTable = null;
        $from = 0;

        while (($i = \strpos($clx, \chr(0x02), $from)) !== false) {
            // Находим размер pieceTable
            $lcbPieceTable = $this->getLong($i + 1, $clx);
            // Находим pieceTable
            $pieceTable = \substr($clx, $i + 5);

            // Если размер фактический отличается от нужного, то это не то -
            // едем дальше.
            if (\strlen($pieceTable) !== $lcbPieceTable) {
                $from = $i + 1;
                continue;
            }
            // Хотя нет - вроде нашли, break, товарищи!
            break;
        }

        return $pieceTable;
    }

    /**
     * Lookup last character position in Word Document steam.
     *
     * @param string $wordDocStream
     * @return int
     */
    protected function getLastCharacterPosition(string $wordDocStream): int
    {
        // Read few values for separate positions from sizing in CLX
        $ccpText = $this->getLong(0x004C, $wordDocStream);
        $ccpFtn = $this->getLong(0x0050, $wordDocStream);
        $ccpHdd = $this->getLong(0x0054, $wordDocStream);
        $ccpMcr = $this->getLong(0x0058, $wordDocStream);
        $ccpAtn = $this->getLong(0x005C, $wordDocStream);
        $ccpEdn = $this->getLong(0x0060, $wordDocStream);
        $ccpTxbx = $this->getLong(0x0064, $wordDocStream);
        $ccpHdrTxbx = $this->getLong(0x0068, $wordDocStream);

        // With this values, find a value of last character position
        $lastCP = $ccpFtn + $ccpHdd + $ccpMcr + $ccpAtn + $ccpEdn + $ccpTxbx + $ccpHdrTxbx;
        $lastCP += ($lastCP !== 0) + $ccpText;

        return $lastCP;
    }
}
