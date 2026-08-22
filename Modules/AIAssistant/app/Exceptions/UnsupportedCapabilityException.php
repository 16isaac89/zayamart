<?php

namespace Modules\AIAssistant\app\Exceptions;

use Exception;

/**
 * Thrown when ConversationService is asked to do something (tool calling,
 * structured output, ...) the resolved provider doesn't advertise support
 * for — see AIProviderCapabilities and architecture doc Part II §2.
 */
class UnsupportedCapabilityException extends Exception
{
}
