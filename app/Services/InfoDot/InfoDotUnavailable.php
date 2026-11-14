<?php

namespace App\Services\InfoDot;

use RuntimeException;

/** InfoDot could not be reached or answered with a server error: the caller says "try again", not "forbidden". */
class InfoDotUnavailable extends RuntimeException {}
