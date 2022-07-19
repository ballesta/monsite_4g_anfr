<?php

/**
 * Created by PhpStorm.
 * User: bernard
 * Date: 14/07/17
 * Time: 19:41
 */
class Trace
{
    var $environnement_execution = "CLI";
    var $EOL = "<br>";
    var $detail = true;

    function title($title)
    {
        echo $title, $this->EOL;
    }

    function section($s)
    {
        echo $s, $this->EOL;
    }

    function ligne($l)
    {
        if ($this->detail)
            echo $l, $this->EOL;
    }
}