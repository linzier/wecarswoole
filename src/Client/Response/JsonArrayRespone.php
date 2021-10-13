<?php

namespace WecarSwoole\Client\Respone;

class JsonArrayRespone extends ArrayResponse
{
    protected function decodeBody(string $origBody)
    {
        return json_decode($origBody, true);
    }
}
