<?php

namespace Cabuz\Stedi\Events;

/**
 * "transaction.processed": Stedi received and translated a transaction, such as a 277 claim acknowledgment or an 835 remittance.
 */
class TransactionProcessed extends WebhookReceived
{
    //
}
