<?php

namespace Nip\Mail\Transport;

use Swift_Events_EventListener;
use Swift_Transport as SwiftTransport;

/**
 * Class AbstractTransport
 * @package Nip\Mail\Transport
 */
abstract class AbstractTransport implements SwiftTransport
{
    /**
     * The plug-ins registered with the transport.
     *
     * @var array
     */
    public $plugins = [];

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }

    /**
     * Register a plug-in with the transport.
     *
     * @param  \Swift_Events_EventListener $plugin
     * @return void
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        array_push($this->plugins, $plugin);
    }
}
