<?php
// vim: ts=3 sw=3
/*
Copyright 2019, James R.
All rights reserved.

Redistribution and use in source form, with or without modification, are
permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those
of the authors and should not be interpreted as representing official policies,
either expressed or implied, of this project.
 */

/*
function cstrsize ( string $string [, int $offset = 0 [, int $limit ] ] ) : int

	Return the length of a C string including the terminating byte, optionally
	starting from offset and optionally up to limit. 

function cstr ( string $string [, int $offset = 0 [, int $limit ] ] ) : string

	Return a string that has been truncated not to include a terminating byte,
	optionally starting from offset and optionally up to limit.

function copy_bool ( bool &$to, mixed $from ) : mixed

	Copy a variable to a reference, casting it to bool. The casted variable is
	also returned.
 */

function cstrsize (string $s, int $l = 0, int $n = NULL) : int
{
	if (( $len = strlen($s) - $l ) < 0)
		return 0;
	// We can't substr outside of length.
	if (isset($n) && $n < $len)
	{
		$s = substr($s, $l, $n);
		$l = 0;
		$len = $n;
	}
	$n = strpos($s, "\0", $l) - $l;
	return ( ($n === FALSE) ?  $len : $n + 1 );
}

function cstr (string $s, int $l = 0, int $n = NULL) : string
{
	$n = cstrsize($s, $l, $n);
	// Check that we haven't been truncated.
	if (!ord($s[$l + ( $n-1 )]))
		$n--;
	return substr($s, $l, $n);
}

function copy_bool (bool &$to = NULL, $from) : bool
{
	$to = (bool)$from;
	return $to;
}
?>
