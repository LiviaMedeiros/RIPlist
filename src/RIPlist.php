<?php declare(strict_types = 1);
class RIPlist {
	private const DRYRUN = false; // for internal debug purposes
	private static function write(string $filepath, array $f, Imagick $src) {
		$img = clone $src;
		$img->cropImage($f['width'], $f['height'], $f['x'], $f['y']);
		$img->setImagePage(0, 0, 0, 0);
		$img->stripImage(); // does not strip dates and some metadata
		self::DRYRUN || $img->writeImage($filepath);
		return $img->clear();
	}
	private static function embed(string $filepath, string $data) {
		// consider the file extension to be representative on initial source file only
		return self::DRYRUN || file_put_contents($filepath, gzdecode(base64_decode(trim($data))));
	}
	private static function frame0(string $filepath, array $f, Imagick $src) {
		// logic for additional data such as $f['originalWidth'] should be here
		return is_numeric($f['x']) && is_numeric($f['y']) && is_numeric($f['width']) && is_numeric($f['height']) && self::write($filepath, $f, $src);
	}
	private static function frame3(string $filepath, array $f, Imagick $src) {
		// logic for additional data such as $f['spriteOffset'] or $f['textureRotated'] should be here
		return !empty($f['textureRect']) && preg_match('/^{{(?<x>[0-9]*),(?<y>[0-9]*)},{(?<width>[0-9]*),(?<height>[0-9]*)}}$/', $f['textureRect'], $m) ? self::write($filepath, array_map('intval', $m), $src) : false;
	}
	private static function frame(int $format, ...$args) {
		return match($format) {
			0 => self::frame0(...$args),
			3 => self::frame3(...$args),
			default => false
		};
	}

	public static function rip(array $plist, string $dir_src = '.', string $dir_out = null) {
		if (!empty($plist['textureFileName']) && !empty($plist['textureImageData']))
			return self::embed($dir_src.'/'.$plist['textureFileName'], $plist['textureImageData']);

		(empty($plist['metadata']) || empty($plist['metadata']['textureFileName']) || !is_numeric($plist['metadata']['format']) || !is_array($plist['frames'])) && throw new Exception("Bad plist data");
		$file_src = $dir_src.'/'.$plist['metadata']['textureFileName'];
		is_readable($file_src) || throw new Exception("Bad source file [$file_src]");
		$dir_out = $dir_out ?? './'.pathinfo($plist['metadata']['textureFileName'], PATHINFO_FILENAME);
		is_dir($dir_out) || self::DRYRUN || mkdir($dir_out, 0755, true) || throw new Exception("Can't use output directory [$dir_out]");

		$img_src = new Imagick($file_src);
		foreach ($plist['frames'] as $name => $f)
			self::frame($plist['metadata']['format'], $dir_out.'/'.$name, $f, $img_src);
		return $img_src->clear();
	}
}
