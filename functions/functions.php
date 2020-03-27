<?php

namespace TextAtAnyCost;

// Функция для преобразования doc в plain-text. Для тех, кому "не нужны классы".
if (!\function_exists('TextAtAnyCost\doc2text')) {
    function doc2text($filename)
    {
        $doc = new Doc();
        $doc->read($filename);

        return $doc->parse();
    }
}

if (!\function_exists('TextAtAnyCost\ppt2text')) {
    function ppt2text($filename)
    {
        $ppt = new Ppt();
        $ppt->read($filename);

        return $ppt->parse();
    }
}
