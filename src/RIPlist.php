<?php declare(strict_types = 1);
class RIPlist {
	private const DRYRUN = false; // for internal debug purposes

	public static bool $raw = true;

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
	private static function get_part(array $r, Imagick $src): Imagick {
		return $src->getImageRegion(...self::get_rect($r));
	}
	private static function write(string $filepath, Imagick $img): bool {
		$img->setImagePage($img->getImageWidth(), $img->getImageHeight(), 0, 0);
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
	private static function frame0(array $f, Imagick $src): Imagick {
		$img = self::get_part($f, $src);
		if (!self::$raw) {} // logic for additional data such as $f['originalWidth'] or $f['offsetX'] should be here
		return $img;
	}
	private static function frame1(array $f, Imagick $src): Imagick {
		if (empty($f['frame']))
			return false;
		$img = self::get_part(self::parse_rect($f['frame']), $src);
		if (!self::$raw) {} // logic for additional data such as $f['offset'] or $f['sourceSize'] should be here
		return $img;
	}
	private static function frame2(array $f, Imagick $src): Imagick {
		if (empty($f['frame']))
			return false;
		$img = self::get_part(self::parse_rect($f['frame']), $src);
		if (!self::$raw) { // logic for additional data such as $f['sourceColorRect'] should be here
			empty($f['rotated']) || $img->rotateImage('transparent', 90);
		}
		return $img;
	}
	private static function frame3(array $f, Imagick $src): Imagick {
		if (empty($f['textureRect']))
			return false;
		$img = self::get_part(self::parse_rect($f['textureRect']), $src);
		if (!self::$raw) { // logic for additional data such as $f['spriteOffset'] or $f['spriteSourceSize'] should be here
			empty($f['textureRotated']) || $img->rotateImage('transparent', 90);
		}
		return $img;
	}
	private static function frame(int $format, string $filepath, mixed ...$args): bool {
		return self::write($filepath, match ($format) {
			0 => self::frame0(...$args),
			1 => self::frame1(...$args),
			2 => self::frame2(...$args),
			3 => self::frame3(...$args),
			default => false
		});
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
