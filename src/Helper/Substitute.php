<?php

namespace WcRecurring\Helper;

class Substitute
{
    private $model;

    public function __construct($model)
    {
        $this->model = (array) $model;
    }

    public function apply($target)
    {
        foreach ($target as $k => $v) {
            $this->model[$k] = $v;
        }
    }

    public function message($message)
    {
        preg_match_all('/{([0-9A-Z_]+)}/', $message, $matches);

        foreach ($matches[1] as $match) {
            $lowerMatch = strtolower($match);
            if (isset($this->model[$lowerMatch])) {
                $rep = $this->model[$lowerMatch];
            } else {
                $rep = '';
            }

            $message = str_replace('{'.$match.'}', $rep, $message);
        }

        return $message;
    }
}
