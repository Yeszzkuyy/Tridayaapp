<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guard konsistensi bahasa: en.json & id.json wajib paritas kunci,
 * dan setiap kunci __() literal di view/app wajib ada terjemahannya.
 */
class LangParityTest extends TestCase
{
    private static function langPath(string $locale): string
    {
        return dirname(__DIR__, 2) . "/lang/{$locale}.json";
    }

    private static function load(string $locale): array
    {
        return json_decode(file_get_contents(self::langPath($locale)), true) ?: [];
    }

    public function test_en_and_id_json_have_identical_key_sets(): void
    {
        $en = array_keys(self::load('en'));
        $id = array_keys(self::load('id'));
        sort($en);
        sort($id);

        $missingInId = array_diff($en, $id);
        $missingInEn = array_diff($id, $en);

        $this->assertSame([], array_values($missingInId), 'Kunci di en.json tapi tidak ada di id.json: ' . implode(', ', array_slice($missingInId, 0, 20)));
        $this->assertSame([], array_values($missingInEn), 'Kunci di id.json tapi tidak ada di en.json: ' . implode(', ', array_slice($missingInEn, 0, 20)));
    }

    public function test_every_literal_translation_key_is_translated(): void
    {
        $en = self::load('en');
        $id = self::load('id');
        $missing = [];

        foreach (['resources/views', 'app'] as $dir) {
            $base = dirname(__DIR__, 2) . '/' . $dir;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!preg_match('/\.php$/', $file->getFilename())) continue;
                $src = file_get_contents($file->getPathname());
                if (preg_match_all("/__\(\s*'((?:[^'\\\\\\\\]|\\\\\\\\.)*)'/su", $src, $m)) {
                    foreach ($m[1] as $key) {
                        $key = stripcslashes($key);
                        if (!array_key_exists($key, $en) || !array_key_exists($key, $id)) {
                            $missing[] = $key . ' (' . substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1) . ')';
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Kunci __() belum diterjemahkan: ' . implode('; ', array_slice(array_unique($missing), 0, 20)));
    }
}
