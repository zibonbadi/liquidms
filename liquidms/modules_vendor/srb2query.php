<?php
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
class SRB2Query::

function SetTimeout (int $milliseconds) : bool

	Set timeout for packet receiving. If a connection is already open, the
	an attempt is made to apply the timeout. FALSE is returned if something
	happens! The default timeout is two milliseconds.

function SetRetries (int $tries) : void

	Set the number of retries after an invalid packet is received and until a
	good packet or receive times out. By default, NO MERCY.

function Close () : void

	Free resources. The timeout and retry count persist.

function Ask (string $host, int $port) : bool

	Ask a server for server info and player info. FALSE is returned if something
	bad happened.

function Info (string &$addr) : array

	Return an array of serverinfo and playerinfo. The IP address that was asked
	is returned in addr.

	The array has the following keys:

	'version' => [
		'major' => (int),
		'minor' => (int),
		'patch' => (int),
		'name'  => (string),

			Name of the mod--SRB2 or SRB2Kart.
	],

	'servername'         => (string),

	'mods'               => (bool),
	'cheats'             => (bool),
	'dedicated'          => (bool),

	'password-protected' => (bool),

	'players' => [
		'count' => (int),

			Players currently connected.

		'max'   => (int),

			The limit of players allowed to connect.

		'list'  => [
			[
				'name'    => (string),
				'team'    => (string),

					'Red' and 'Blue' in CTF, 'Spectator' when spectating, and
					'Playing' otherwise.

				'score'   => (int),

					SRB2 only; POINTS!

				'rank'    => (int),

					SRB2Kart only; Advancing rank over races.

				'seconds' => (int),

					Number of seconds since the player joined the server.
			]...
		],
	],

	'level' => [
		'seconds' => (int),

			Numbers of seconds since the level started.

		'title'   => (string),

			Inlcuding ZONE and the act number (if either applicable).

		'mapmd5'  => (string),

			The map MD5 checksum in hexadecimal representation.
	],

	'gametype'  => (string),
	'kartspeed' => (string),

		SRB2Kart Race gametype only; The racing speed.

function FileInfo () : array

	Return an array of files added to server previously queried with
	SRB2Query::Ask with the following keys.

	[
		'name'     => (string),
		'bytes'    => (int),
		'md5sum'   => (string),

			The file's MD5 checksum in hexadecimal representation.

		'toobig'   => (bool),

			The file is too big so server is not willing to send it.

		'download' => (bool),

			The server is willing to send the file.
	]...

function Colorize (string $string) : string

	Convert SRB2 byte color codes and caret codes to CSS colored span tags in
	string and return it. See SRB2Query::$colors for the list of colors.

array $colors

	The list of SRB2 name colors.
 */

include __DIR__ . '/util.php';

// THE BIG CLASS
class SRB2Query
{
	public $colors = [
		'inherit',
		'#df00df',
		'#ffff0f',
		'#69e046',
		'#7373ff',
		'#ff3f3f',
		'#a7a7a7',
		'#ff9736',
		'#55c8ff',
		'#cf7fcf',
		'#d7bb43',
		'#c7e494',
		'#c4c4e1',
		'#f3a3a3',
		'#bf7b4b',
		'#ffc7a7',
	];

	// doomdef.h
	private const TICRATE            = 35;

	// doomstat.h
	private const GT_COOP            = 0;
	private const GT_COMPETITION     = 1;
	private const GT_RACE            = 2;
	private const GT_MATCH           = 3;
	private const GT_TEAMMATCH       = 4;
	private const GT_TAG             = 5;
	private const GT_HIDEANDSEEK     = 6;
	private const GT_CTF             = 7;

	private const GF_REDFLAG         = 1;
	private const GF_BLUEFLAG        = 2;

	// d_clisrv.h
	private const PT_ASKINFO         = 12;
	private const PT_SERVERINFO      = 13;
	private const PT_PLAYERINFO      = 14;

	private const PT_TELLFILESNEEDED = 34;
	private const PT_MOREFILESNEEDED = 35;

	private const SV_SPEEDMASK       = 0x03;
	private const SV_LOTSOFADDONS    = 0x20;
	private const SV_DEDICATED       = 0x40;
	private const SV_PASSWORD        = 0x80;

	// d_netfil.c
	private const NETFIL_WONTSEND    = 32;
	private const NETFIL_WILLSEND    = 16;

	private const pkformats = [
		self::PT_SERVERINFO => [
			'format' =>
			'Cversion/'        .
			'Csubversion/'     .
			'Cnumberofplayer/' .
			'Cmaxplayer/'      .
			'Cgametype/'       .
			'Cmodifiedgame/'   .
			'Ccheatsenabled/'  .
			'Cisdedicated/'    .
			'Cfileneedednum/'  .
			'cadminplayer/'    .
			'Vtime/'           .
			'Vleveltime/'      .
			'a32servername/'   .
			'a8mapname/'       .
			'a33maptitle/'     .
			'a16mapmd5/'       .
			'Cactnum/'         .
			'Ciszone/'         .
			'a*fileneeded',

			'strings' => [
				'servername',
				'mapname',
				'maptitle',
			],

			'minimum' => 109,
		],
		self::PT_PLAYERINFO => [
			'format' =>
			'Cnode/'           .
			'a22name/'         .
			'a4address/'       .
			'Cteam/'           .
			'Cskin/'           .
			'Cdata/'           .
			'Vscore/'          .
			'vtimeinserver',

			'strings' => [
				'name',
			],

			'minimum' => 36,
		],
		self::PT_MOREFILESNEEDED => [
			'format' =>
			'Vfirst/'          .
			'Cnum/'            .
			'Cmore/'           .
			'a*files',

			'minimum' => 6,
		],
	];

	// w_wad.h
	private const MAX_WADPATH        = 512;

	private $so;
	private $addr, $port;

	private $timeout = [
		'sec'  => 2,
		'usec' => 0,
	];
	private $retries = 0;

	private $lotsofaddons;
	private $fileneedednum;
	private $fileneeded;

	private function Versionname ($pk)
	{
		switch ($pk['version'])
		{
		case 100:
		case 110:
			return 'SRB2Kart';
		default:
			return 'SRB2';
		}
	}

	private function Gametype ($pk)
	{
		// SRB2Kart
		if ($pk['version'] == 100 || $pk['version'] == 110)
		{
			$kartnames = [
				self::GT_COMPETITION => 'Battle',
			];
			if (isset($kartnames[$pk['gametype']]))
				return $kartnames[$pk['gametype']];
		}
		$names = [
			self::GT_COOP        => 'Co-op',
			self::GT_COMPETITION => 'Competition',
			self::GT_RACE        => 'Race',
			self::GT_MATCH       => 'Match',
			self::GT_TEAMMATCH   => 'Team Match',
			self::GT_TAG         => 'Tag',
			self::GT_HIDEANDSEEK => 'Hide and Seek',
			self::GT_CTF         => 'CTF',
		];
		$name = $names[$pk['gametype']];
		return ( ($name) ? $name : 'Unknown' );
	}

	private function Zonetitle ($pk)
	{
		$maptitle = $pk['maptitle'];
		if ($pk['iszone'])
			$maptitle .= ' ZONE';
		if ($pk['actnum'])
			$maptitle .= ' ' . $pk['actnum'];
		return $maptitle;
	}

	private function Unkartvars (&$info, $pk)
	{
		$c = $pk['isdedicated'];

		$this->lotsofaddons = ( $c & self::SV_LOTSOFADDONS );

		if ($pk['gametype'] == self::GT_RACE)
		{
			$speeds = [
				'Easy',
				'Normal',
				'Hard',
			];
			$speed = $speeds[( $c & self::SV_SPEEDMASK )];
			$info['kartspeed'] = ( ($speed) ? $speed : 'Too fast' );
		}
		copy_bool($info['dedicated'],          ( $c & self::SV_DEDICATED ));
		copy_bool($info['password-protected'], ( $c & self::SV_PASSWORD  ));
	}

	private function Checksum ($p, $l)
	{
		$n = strlen($p) - $l;
		$c = 0x1234567;
		for ($i = 0; $i < $n; ++$i)
			$c += ord($p[$l + $i]) * ($i + 1);
		return $c;
	}

	private function Packet ($pk)
	{
		switch ($pk['type'])
		{
		case self::PT_ASKINFO:
			/* 1 byte version and 4 byte time */
			$u = pack('x5');
			break;
		case self::PT_TELLFILESNEEDED:
			$u = pack('V',
				$pk['filesneedednum']);
			break;
		}
		/* 1 byte ack and 1 byte ackreturn, finally 1 byte padding */
		$buf = pack('xxCx', $pk['type']) . $u;
		return pack('V', self::Checksum($buf, 0)) . $buf;
	}

	private function Unpk ($pk, $n = 0)
	{
		$pkf = self::pkformats[$pk['type']];
		// I can get away with just 'minimum' for now because there aren't any
		// variable length packets in array format. That sounds stupid anyway.
		$n = $n * $pkf['minimum'];
		if ($n + $pkf['minimum'] > strlen($pk['buffer']))
			return FALSE;
		$t = unpack($pkf['format'], $pk['buffer'], $n);
		if (isset($pkf['strings']))
		{
			foreach ($pkf['strings'] as $str)
				$t[$str] = cstr($t[$str]);
		}
		return $t;
	}

	private function Unpacket ($p, $type, $unpk = TRUE)
	{
		$n = strlen($p);
		if ($n < 8) // Header
			return FALSE;
		if (unpack('V', $p)[1] != self::Checksum($p, 4)) // Checksum mismatch
			return FALSE;
		if (( $pk['type'] = ord($p[6]) ) != $type)
			return FALSE;
		if (!( $pkf = self::pkformats[$pk['type']] ))
			return FALSE;
		if ($n < $pkf['minimum'])
			return FALSE;
		$pk['buffer'] = substr($p, 8);
		if ($unpk)
		{
			$pk = array_merge($pk, self::Unpk($pk));
			unset($pk['buffer']);
		}
		return $pk;
	}

	private function Unfileneeded (&$fileinfo, $fileneedednum, $fileneeded)
	{
		$l = 0;
		for ($i = 0; $i < $fileneedednum; ++$i)
		{
			$pk = unpack(
				'Cstatus/' .
				'Vsize',
				$fileneeded, $l);
			$l += 5;

			$pk['name']   = cstr($fileneeded, $l, self::MAX_WADPATH);
			$l +=       cstrsize($fileneeded, $l, self::MAX_WADPATH);

			$pk['md5sum'] = bin2hex(substr($fileneeded, $l, 16));
			$l += 16;

			copy_bool($pk['toobig'],  !( $pk['status'] & self::NETFIL_WILLSEND ));
			copy_bool($pk['download'],
				!( $pk['toobig'] || ( $pk['status'] & self::NETFIL_WONTSEND ) ));

			unset($pk['status']);

			$fileinfo[] = $pk;
		}
	}

	private function Send ($pk)
	{
		$buf = self::Packet($pk);
		$b   = strlen($buf);
		$n   = socket_sendto($this->so, $buf, $b, 0, $this->addr, $this->port);
		return ( $n == $b );
	}

	private function Settimeoutopt ()
	{
		return socket_set_option($this->so, SOL_SOCKET,
			SO_RCVTIMEO, $this->timeout);
	}

	private function Sendto ($host, $port, $pk)
	{
		if (!$this->so)
		{
			$this->so = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			if (!( $this->so && self::Settimeoutopt() ))
				return FALSE;
		}
		$this->addr = gethostbyname($host);
		$this->port = $port;
		return self::Send($pk);
	}

	private function Read ($type, $unpk = TRUE)
	{
		$tries = 0;
		do
		{
			$n = socket_recvfrom($this->so, $buf, 1450, 0, $addr, $port);
			if ($n === FALSE ||
				$addr != $this->addr ||
				$port != $this->port)
			{
				return FALSE;
			}
			$pk = self::Unpacket($buf, $type, $unpk);
		}
		while ($pk === FALSE && $tries++ < $this->retries) ;
		return $pk;
	}

	function SetTimeout (int $ms) : bool
	{
		$this->timeout = [
			'sec'  => $ms / 1000,
			'usec' => $ms % 1000 * 1000,
		];
		if ($this->so)
			return self::Settimeoutopt();
		else
			return TRUE;
	}

	function SetRetries (int $n) : void
	{
		$this->retries = $n;
	}

	function Close () : void
	{
		if ($this->so)
			socket_close($this->so);
		unset($this->addr, $this->port,
			$this->lotsofaddons, $this->fileneedednum, $this->fileneeded);
	}

	function Ask (string $host, int $port) : bool
	{
		return self::Sendto($host, $port, [ 'type' => self::PT_ASKINFO ]);
	}

	function Info (string &$addr = NULL)
	{
		if (!( $pk = self::Read(self::PT_SERVERINFO) ))
			return FALSE;

		$this->fileneedednum = $pk['fileneedednum'];
		$this->fileneeded    = $pk['fileneeded'];

		$version    = $pk['version'];
		$subversion = $pk['subversion'];

		// SRB2Kart 1.0.4 and 1.10.0
		if (( $version == 100 && $subversion == 4 ) ||
			$version == 110)
		{
			self::Unkartvars($t, $pk);
		}
		else
		{
			copy_bool($t['dedicated'], $pk['isdedicated']);
			$this->lotsofaddons = FALSE;
		}

		$t['version'] = [
			'major' => $version / 100,
			'minor' => $version % 100,
			'patch' => $subversion,
		];
		$t['version']['name'] = self::Versionname($pk);

		$t['servername'] = $pk['servername'];

		$t['players'] = [
			'count' => $pk['numberofplayer'],
			'max'   => $pk['maxplayer'],
		];

		$t['gametype'] = self::Gametype($pk);

		copy_bool($t['mods'],   $pk['modifiedgame']);
		copy_bool($t['cheats'], $pk['cheatsenabled']);

		$t['level'] = [
			'seconds' => $pk['leveltime'] / self::TICRATE,
			'title'   => self::Zonetitle($pk),
			'md5sum'  => bin2hex($pk['mapmd5']),
		];

		$info = $t;
		unset($t);

		$addr = $this->addr;

		if (!( $mpk = self::Read(self::PT_PLAYERINFO, FALSE) ))
			return $info;

		$teams = [
			self::GF_REDFLAG  => 'Red',
			self::GF_BLUEFLAG => 'Blue',
			0                 => 'Playing',
			255               => 'Spectator',
		];

		$info['players']['list'] = [];

		for ($i = 0; $i < 32; ++$i)
		{
			if (!( $pk = self::Unpk($mpk, $i) ))
				break;
			if ($pk['node'] < 255)
			{
				$t['name']    = $pk['name'];
				$team = $teams[$pk['team']];
				$t['team']    = ( ($team) ? $team : 'Unknown' );
				$t[( ($version == 100 || $version == 110) ?
					'rank' : 'score' )] = $pk['score'];
				$t['seconds'] = $pk['timeinserver'];

				$info['players']['list'][] = $t;
			}
		}

		return $info;
	}

	function Fileinfo () : array
	{
		self::Unfileneeded($fileinfo, $this->fileneedednum, $this->fileneeded);
		if ($this->lotsofaddons)
		{
			$start = $this->fileneedednum;
			do
			{
				if (!self::Send([
					'type'           => self::PT_TELLFILESNEEDED,
					'filesneedednum' => $start,
				]))
					break;
				if (!( $pk = self::Read(self::PT_MOREFILESNEEDED) ))
					break;
				self::Unfileneeded($fileinfo, $pk['num'], $pk['files']);
				$start += $pk['num'];
			}
			while (( $pk['more'] )) ;
		}
		return $fileinfo;
	}

	function Colorize (string $s) : string
	{
		// Probably not the pinnacle of performance.
		for ($i = 0x00; $i <= 0x0F; ++$i)
		{
			$codes[$i] = chr(0x80 + $i);
			$alts [$i] = '^' . dechex($i);
		}

		// Remove anything that is not printable ASCII and sanitize!
		$s = '<span style="color:' . $this->colors[0] . '">' .
			htmlentities(preg_replace('/[\x00-\x19\x7F-\xFF]/', '',
				str_replace($codes, $alts, $s))) . '</span>';

		// Doing this backward for a good reason; so that higher numbers get a
		// chance to be matched!
		// Not necessary anymore though...
		for ($i = 0x0F; $i >= 0x00; --$i)
		{
			$s = str_replace($alts[$i],
				'</span><span style="color:' . $this->colors[$i] . ';">',
				$s);
		}

		return $s;
	}
}
?>
