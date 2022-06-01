<?php

namespace WecarSwoole\Client\Response;

class JsonArrayResponse extends ArrayResponse
{
    protected function decodeBody($origBody)
    {
        if (is_array($origBody)) {
            return $origBody;
        }

        if (!$origBody) {
            return [];
        }

        return json_decode($origBody, true) ?: [];
    }
}
