<?php declare(strict_types = 1);
class RIPlist {
	private const DRYRUN = false; // for internal debug purposes
	private static function get_rect(array $r): array {
		return array_map(fn($key) => isset($r[$key]) && is_numeric($r[$key])
			? intval($r[$key])
			: throw new Exception("Bad value [$key]"),
		['width', 'height', 'x', 'y']);
	}
	private static function parse_rect(string $r): array {
		return preg_match('/^{{(?<x>[0-9]*),(?<y>[0-9]*)},{(?<width>[0-9]*),(?<height>[0-9]*)}}$/', $r, $m)
			? $m
			: throw new Exception("Bad rect string [$r]");
	}
	private static function write(string $filepath, array $r, Imagick $src): bool {
		$img = $src->getImageRegion(...self::get_rect($r));
		$img->setImagePage(0, 0, 0, 0);
		$img->stripImage(); // does not strip dates and some metadata
		self::DRYRUN || $img->writeImage($filepath);
		return $img->clear();
	}
	private static function embed(string $filepath, string $data): bool {
		// consider the file extension to be representative on initial source file only
		return self::DRYRUN || boolval(file_put_contents($filepath,
			gzdecode(base64_decode(trim($data)
			) ?: throw new Exception("Failed to decode embedded base64")
			) ?: throw new Exception("Failed to decode embedded gzip")));
	}
	private static function frame0(string $filepath, array $f, Imagick $src): bool {
		// logic for additional data such as $f['originalWidth'] or $f['offsetX'] should be here
		return self::write($filepath, $f, $src);
	}
	private static function frame1(string $filepath, array $f, Imagick $src): bool {
		// logic for additional data such as $f['offset'] or $f['sourceSize'] should be here
		return !empty($f['frame']) && self::write($filepath, self::parse_rect($f['frame']), $src);
	}
	private static function frame2(string $filepath, array $f, Imagick $src): bool {
		// logic for additional data such as $f['sourceColorRect'] or $f['rotated'] should be here
		return !empty($f['frame']) && self::write($filepath, self::parse_rect($f['frame']), $src);
	}
	private static function frame3(string $filepath, array $f, Imagick $src): bool {
		// logic for additional data such as $f['spriteOffset'] or $f['textureRotated'] should be here
		return !empty($f['textureRect']) && self::write($filepath, self::parse_rect($f['textureRect']), $src);
	}
	private static function frame(int $format, mixed ...$args): bool {
		return match ($format) {
			0 => self::frame0(...$args),
			1 => self::frame1(...$args),
			2 => self::frame2(...$args),
			3 => self::frame3(...$args),
			default => false
		};
	}

	public static function rip(array $plist, string $dir_src = '.', ?string $dir_out = null): bool {
		if (!empty($plist['textureFileName']) && !empty($plist['textureImageData']))
			return self::embed($dir_src.'/'.$plist['textureFileName'], $plist['textureImageData']);

		(empty($plist['metadata']) || empty($plist['metadata']['textureFileName']) || !is_numeric($plist['metadata']['format']) || !is_array($plist['frames'])) && throw new Exception("Bad plist data");
		$file_src = $dir_src.'/'.$plist['metadata']['textureFileName'];
		is_readable($file_src) || throw new Exception("Bad source file [$file_src]");
		$dir_out ??= './'.pathinfo($plist['metadata']['textureFileName'], PATHINFO_FILENAME);
		is_dir($dir_out) || self::DRYRUN || mkdir($dir_out, 0755, true) || throw new Exception("Can't use output directory [$dir_out]");

		$img_src = new Imagick($file_src);
		foreach ($plist['frames'] as $name => $f)
			self::frame($plist['metadata']['format'], $dir_out.'/'.$name, $f, $img_src);
		return $img_src->clear();
	}
}
