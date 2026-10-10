<?php

namespace App\Domain\Cases;

use RuntimeException;

final class CatalogManifest
{
    /** @var list<array<string, mixed>>|null */
    private ?array $catalog = null;

    /** @return list<array<string, mixed>> */
    public function styles(): array
    {
        return $this->catalog ??= json_decode((string) file_get_contents(database_path('seeders/fixtures/catalog-v2/catalog.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->styles(), JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function imageErrors(bool $requireFiles = true): array
    {
        $errors = [];
        $paths = [];
        $skus = [];
        $combinations = [];
        foreach ($this->styles() as $style) {
            foreach ($style['variants'] as $variant) {
                $skus[] = $variant['sku'];
                $combinations[] = implode('|', [$style['key'], $style['fit'], $style['composition'], $style['gsm'], $variant['color'], $variant['size']]);
                if (! in_array($variant['color'], $style['colors'], true) || ! in_array($variant['size'], $style['sizes'], true)
                    || ! preg_match('/^#[0-9A-F]{6}$/D', $variant['details']['color_code'])) {
                    $errors[] = 'Atributos incoherentes: '.$variant['sku'];
                }
                $views = array_column($variant['details']['images'], 'view');
                sort($views);
                if ($views !== ['back', 'detail', 'front', 'three-quarter']) {
                    $errors[] = 'Vistas incoherentes: '.$variant['sku'];
                }
                if (count($variant['details']['images']) !== 4) {
                    $errors[] = 'Galería incompleta: '.$variant['sku'];
                }
                foreach ($variant['details']['images'] as $image) {
                    $expected = '/images/catalog/v1/'.$style['key'].'/'.($variant['color'] === 'Negro' ? 'negro' : 'natural').'/'.$image['view'].'.png';
                    if ($image['path'] !== $expected || $image['width'] < 1 || $image['height'] < 1 || empty($image['alt'])) {
                        $errors[] = 'Referencia de imagen incoherente: '.$variant['sku'];
                    }
                    $paths[$image['path']] = $image;
                }
            }
        }
        if (count($this->styles()) !== 6 || count($skus) !== 36 || count(array_unique($skus)) !== 36 || count(array_unique($combinations)) !== 36 || count($paths) !== 48) {
            $errors[] = 'Cobertura distinta de 36 variantes / 48 imágenes.';
        }
        foreach ($paths as $path => $image) {
            if (! $requireFiles) {
                continue;
            }
            $file = public_path($path);
            if (! is_file($file)) {
                $errors[] = 'Imagen ausente: '.$path;

                continue;
            }
            $size = getimagesize($file);
            $header = file_get_contents($file, false, null, 0, 26);
            if (! $size || $size[0] !== $image['width'] || $size[1] !== $image['height'] || $size[2] !== IMAGETYPE_PNG || $header === false || strlen($header) < 26 || ! in_array(ord($header[25]), [4, 6], true)) {
                $errors[] = 'Dimensiones o alfa inválidos: '.$path;
            }
        }

        return $errors;
    }

    public function assertImages(bool $requireFiles = true): void
    {
        if ($errors = $this->imageErrors($requireFiles)) {
            throw new RuntimeException(implode("\n", $errors));
        }
    }

    public function expectsPath(string $path): bool
    {
        foreach ($this->styles() as $style) {
            foreach ($style['variants'] as $variant) {
                if (in_array($path, array_column($variant['details']['images'], 'path'), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function visibleDetails(array $details): array
    {
        $visible = fn (string $path): bool => str_starts_with($path, 'https://') || (str_starts_with($path, '/images/catalog/') && is_file(public_path($path)));
        if (isset($details['images'])) {
            $details['images'] = array_values(array_filter($details['images'], fn ($image) => $visible($image['path'])));
        }
        if (isset($details['photos'])) {
            $details['photos'] = array_values(array_filter($details['photos'], $visible));
        }

        return $details;
    }
}
