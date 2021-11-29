<?php declare(strict_types=1);

namespace mii\sxgeo\console;

use Mii;
use mii\console\Controller;
use RuntimeException;
use ZipArchive;

class Sxgeo extends Controller
{
    protected string $localPath;
    protected string $downloadUrl;

    private string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.45 Safari/537.36';

    /**
     * Update local SxGeo data file
     */
    public function update(
        string $path = '@tmp/SxGeoCity.dat',
        string $url = 'https://sypexgeo.net/files/SxGeoCity_utf8.zip'
    )
    {
        $this->localPath = Mii::resolve($path);
        $this->downloadUrl = $url;

        $zipFile = tempnam(sys_get_temp_dir(), "sxg");
        $zipResource = fopen($zipFile, "w");

        try {
            if ($this->downloadTo($zipResource)) {
                $unzippedPath = $this->extract($zipFile);
                if (!rename($unzippedPath, $this->localPath)) {
                    $this->error("Failed to rename file $unzippedPath to {$this->localPath}");
                    return;
                }
                $this->info('Update done');
            }
        } catch (\Throwable $t) {
            $this->error($t->getMessage());
        } finally {
            fclose($zipResource);
            unlink($zipFile);
        }
    }

    private function downloadTo($zipResource): bool
    {
        $this->info('Try to download');

        $ch = curl_init($this->downloadUrl);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, file_exists($this->localPath)
            ? ["If-Modified-Since: " . gmdate('D, d M Y H:i:s', filemtime($this->localPath)) . " GMT"]
            : []
        );
        curl_setopt($ch, CURLOPT_FILE, $zipResource);

        if (curl_exec($ch) === false) {
            throw new RuntimeException(curl_error($ch));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code === 304) {
            $this->info("Local db is up to date");
            return false;
        }

        if ($code !== 200) {
            $this->error("Download failed. Code $code");
            return false;
        }
        return true;
    }


    /**
     * @param string $from
     * @return string Filename to extracted file
     */
    private function extract(string $from): string
    {
        $this->info('Extracting file from archive...');

        $zip = new ZipArchive();
        $res = $zip->open($from);

        if ($res !== true) {
            throw new RuntimeException("Extraction failed: error code $res");
        }

        $fileName = $zip->getNameIndex(0);

        $extractPath = dirname($from);

        $success = $zip->extractTo($extractPath, $fileName);
        $zip->close();

        if (!$success) {
            throw new RuntimeException($zip->getStatusString());
        }

        return $extractPath . DIRECTORY_SEPARATOR . $fileName;
    }
}