<?php

namespace App\AI;

// Thrown by ReminderWriter when the API fails before or during the stream. Same role as
// DraftFailed: the SDK's exceptions stop here, so the controller never imports the SDK.
final class ReminderFailed extends \RuntimeException {}
