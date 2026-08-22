<?php

namespace Modules\AIAssistant\app\Exceptions;

use Exception;

/**
 * Thrown by a provider adapter on transport/auth/provider-side failure.
 * ConversationService catches this to drive graceful degradation (and,
 * later, failover — architecture doc Part II §9 / Part I §... "Failover").
 */
class AIProviderException extends Exception
{
}
