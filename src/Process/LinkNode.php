<?php

namespace WecarSwoole\Process;

/**
 * 链表节点
 */
class LinkNode
{
    private $size;
    private $time;
    private $next;

    public function __construct(int $size, int $time)
    {
        $this->size = $size;
        $this->time = $time;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function time(): int
    {
        return $this->time;
    }

    public function next(): ?LinkNode
    {
        return $this->next;
    }

    public function setNext(LinkNode $node = null)
    {
        $this->next = $node;
    }
}
