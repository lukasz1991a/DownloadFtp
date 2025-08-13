<?php
error_reporting(-1);
echo 'aaa';
/**
 * Skrypt do pobierania plików na serwer
 * 1. Umieść plik na serwerze
 * 2. Uzupełnij konfigurację
 * 3. Odpal skrypt w konsoli-przetestowane dla php84.
 *      - pierwszy raz stworzy listę plików do pobrania w $downloadToLocation,
 *      - kolejne razy będzie pobierał pliki z listy.
 * 4. odpalaj tak długo, aż zobaczysz "Brak plików do pobrania w kolejce"
 */

class DownloadFtp
{
    /**
     * Skrypt pobierania wielu plików z FTP równolegle — PHP 8.4
     */
// ======== KONFIGURACJA ========
    private string $ftp_server;
    private string $ftp_user;
    private string $ftp_pass;
    private string $ftp_base_dir = "/"; // katalog na FTP, z którego pobierasz
    private int $max_parallel = 10; // ile plików naraz pobierać
    private int $parts = 2000; // Ile paczek ma pobrać
    private string $list_file;
    private string $downloadToLocation; //Gdzie ma zapisać pliki
    private int $download_limit = 5000;  // ile maksymalnie plików w tym uruchomieniu
    private array $options;

    public function __construct()
    {
        $this->setVars();
        $today = date('Y-m-d-').md5($this->ftp_server.$this->ftp_user.$this->ftp_base_dir);
        $this->list_file = __DIR__ . "/download-kolejka$today.txt";
        $this->downloadToLocation ??= __DIR__ . '/download-' . $today;
        if (!is_dir($this->downloadToLocation)) mkdir($this->downloadToLocation, 0777, true);
        // Wczytaj listę z pliku
        if (!file_exists($this->list_file)) {
            $this->createFileListToDownload();
            echo("✅ Lista plików zapisana w: $this->list_file\n");
        }
    }

    private function setVars(): void
    {
        echo "xxxx \n\n\n";
        $this->options = getopt("s:l:f:p:t:c:");
        print_r($this->options);
        echo "xxxx \n\n\n";
        $this->options = getopt("s:l:f:p:t:c:d:");
        $this->set('s', 'ftp_server');
        $this->set('l', 'ftp_user');
        $this->set('p', 'ftp_pass');
        $this->set('f', 'ftp_base_dir', true);
        $this->set('t', 'downloadToLocation', true);
        $this->set('c', 'max_parallel');
        $this->set('d', 'download_limit');
    }

    private function normalizePath($path) : string
    {
        // Usuń spacje na początku/końcu
        $path = trim($path);

        // Zamień backslash (\) na slash (/)
        $path = str_replace('\\', '/', $path);

        // Usuń wszystkie nadmiarowe slashe
        $path = preg_replace('#/+#', '/', $path);

        // Usuń slash na końcu, jeśli jest (chyba że ścieżka to "/")
        $path = rtrim($path, '/');

        // Dodaj jeden slash na początku
        return '/' . ltrim($path, '/');
    }

    private function set(string $k, string $v, bool $isPath = false): void
    {
        if (isset($this->options[$k])) {
            $this->$v = $isPath ? $this->normalizePath($this->options[$k]) : $this->options[$k];
        }
    }

    private function createFileListToDownload(): void
    {
        echo "Tworzenie listy plików do pobrania\n\n\n\n\n\n\n\n";
        $conn_id = ftp_connect($this->ftp_server);
        if (!$conn_id) die("❌ Nie można połączyć z FTP\n");
        echo $this->ftp_pass . "\n\n\n\n\n\n\n\n";
        if (!ftp_login($conn_id, $this->ftp_user, $this->ftp_pass)) die("Błędne dane logowania\n");
        ftp_pasv($conn_id, true);
        $fh = fopen($this->list_file, "w");
        $this->scan_ftp($conn_id, $this->ftp_base_dir ?: ".", $fh);
        fclose($fh);
        ftp_close($conn_id);
    }

// Funkcja tworząca uchwyt curl
    private function init_curl_handle(string $remote_file): CurlHandle
    {
        $ch = curl_init();

//        $local_path = $this->downloadToLocation . '/' . ltrim($remote_file, '/');
        $local_path = $this->downloadToLocation . '/' . preg_replace('#^'.ltrim($this->ftp_base_dir, '/').'#', '', ltrim($remote_file, '/'));
        if (!is_dir(dirname($local_path))) {
            mkdir(dirname($local_path), 0777, true);
        }

        $fp = fopen($local_path, 'w');
        if ($fp === false) {
            throw new RuntimeException("Nie można utworzyć pliku: $local_path");
        }

        $url = "ftp://{$this->ftp_user}:{$this->ftp_pass}@{$this->ftp_server}{$remote_file}";

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        return $ch;
    }

// Funkcja pobierania równoległego
    private function download_parallel(array $files): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $queue = $files;
        $active = null;
        $completed = [];

        for ($i = 0; $i < $this->max_parallel && !empty($queue); $i++) {
            $file = array_shift($queue);
            $ch = $this->init_curl_handle($file);
            curl_multi_add_handle($mh, $ch);
            $handles[spl_object_id($ch)] = $file;
        }

        do {
            curl_multi_exec($mh, $active);
            curl_multi_select($mh);

            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $id = spl_object_id($ch);
                $file = $handles[$id];

                if ($info['result'] === CURLE_OK) {
//                    echo "✅ Pobrano: $file\n";
                    $completed[] = $file;
                } else {
                    echo "❌ Błąd: $file — " . curl_error($ch) . "\n";
                }

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($handles[$id]);

                if (!empty($queue)) {
                    $next_file = array_shift($queue);
                    $next_ch = $this->init_curl_handle($next_file);
                    curl_multi_add_handle($mh, $next_ch);
                    $handles[spl_object_id($next_ch)] = $next_file;
                }
            }
        } while ($active || !empty($queue));

        curl_multi_close($mh);
        return $completed;
    }

    /**
     * SKanowanie FTP
     */
    private function scan_ftp($conn_id, string $remote_dir, $fh): void
    {
        $items = ftp_rawlist($conn_id, $remote_dir);
        if (!$items) return;

        foreach ($items as $item) {
            $info = preg_split("/\s+/", $item, 9);
            if (count($info) < 9) continue;
            $permissions = $info[0];
            $name = $info[8];
            if ($name === "." || $name === "..") continue;

            $remote_path = rtrim($remote_dir, '/') . '/' . $name;
            if ($permissions[0] === 'd') {
                echo "$remote_path\n";
                $this->scan_ftp($conn_id, $remote_path, $fh);
            } else {
                fwrite($fh, $remote_path . "\n");
            }
        }
    }

    private function downloadPart(): void
    {
        $all_files = file($this->list_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($all_files)) {
            die("Brak plików do pobrania w kolejce\n");
        }

        $to_download = array_slice($all_files, 0, $this->download_limit);
        $remaining = array_slice($all_files, $this->download_limit);

// Pobierz wybraną partię
        $done = $this->download_parallel($to_download);

// Usuń pobrane z kolejki
        if (!empty($done)) {
            $new_queue = array_diff($remaining, $done);
            file_put_contents($this->list_file, implode("\n", $new_queue) . "\n");
            echo "📌 Zaktualizowano kolejkę: " . count($new_queue) . " plików pozostało\n";
        }
        echo "📂 Zakończono pobieranie do: $this->downloadToLocation\n";
    }

    function download(): void
    {
        for ($i = 0; $i < $this->parts; $i++) {
            $this->downloadPart();
        }
    }

}

$start = microtime(true);
$download = new DownloadFtp();
$download->download();
echo $start - microtime(true);
