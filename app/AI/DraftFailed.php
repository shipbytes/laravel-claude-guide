<?php

namespace App\AI;

// The one exception the rest of the app sees when drafting fails. It wraps the SDK's typed
// exceptions so controllers never import the SDK (an architecture test holds that line).
final class DraftFailed extends \RuntimeException {}
