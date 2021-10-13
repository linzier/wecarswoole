<?php

namespace WecarSwoole\Client\Response;

class JsonArrayResponse extends ArrayResponse
{
    protected function decodeBody(string $origBody)
    {
        return json_decode($origBody, true);
    }
}
