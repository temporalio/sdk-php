<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

enum ConnectionState: int
{
    /**
     * Channel is connecting
     *
     * The channel is trying to establish a connection and is waiting to make progress on one of
     * the steps involved in name resolution, TCP connection establishment or TLS handshake.
     * This may be used as the initial state for channels upon creation.
     */
    case Connecting = 1;

    /**
     * Channel is ready for work
     *
     * The channel has successfully established a connection all the way through TLS handshake (or equivalent)
     * and protocol-level handshaking, and all subsequent attempt to communicate have succeeded
     * (or are pending without any known failure).
     */
    case Ready = 2;

    /**
     * Channel has seen a failure but expects to recover
     *
     * There has been some transient failure (such as a TCP 3-way handshake timing out or a socket error).
     * Channels in this state will eventually switch to the CONNECTING state and try to establish a connection again.
     * Since retries are done with exponential backoff, channels that fail to connect will
     * start out spending very little time in this state but as the attempts fail repeatedly,
     * the channel will spend increasingly large amounts of time in this state.
     * For many non-fatal failures (e.g., TCP connection attempts timing out because the server
     * is not yet available), the channel may spend increasingly large amounts of time in this state.
     */
    case TransientFailure = 3;

    /**
     * Channel is idle
     *
     * This is the state where the channel is not even trying to create a connection because of a lack of new
     * or pending RPCs. New RPCs MAY be created in this state.
     * Any attempt to start an RPC on the channel will push the channel out of this state to connecting.
     * When there has been no RPC activity on a channel for a specified IDLE_TIMEOUT,
     * i.e., no new or pending (active) RPCs for this period, channels that are READY or CONNECTING switch to IDLE.
     * Additionally, channels that receive a GOAWAY when there are no active or pending RPCs should also switch
     * to IDLE to avoid connection overload at servers that are attempting to shed connections.
     * We will use a default IDLE_TIMEOUT of 300 seconds (5 minutes).
     */
    case Idle = 0;

    /**
     * Channel has seen a failure that it cannot recover from
     *
     * This channel has started shutting down. Any new RPCs should fail immediately.
     * Pending RPCs may continue running till the application cancels them.
     * Channels may enter this state either because the application explicitly requested a shutdown
     * or if a non-recoverable error has happened during attempts to connect communicate .
     * Channels that enter this state never leave this state.
     */
    case Shutdown = 4;
}
