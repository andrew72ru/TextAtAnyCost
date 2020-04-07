<?php

namespace TextAtAnyCost;

if (!\function_exists('TextAtAnyCost\doc2text')) {
    function doc2text($filename)
    {
        $doc = new Doc();
        $doc->read($filename);

        return $doc->parse();
    }
}
