# Co to 
Jest to skrypt do kopiowania z konta ftp do lokalnego folderu (zastępuje FileZille)

# Przykład uruchomienia
```
curl -s https://raw.githubusercontent.com/lukasz1991a/DownloadFtp/refs/heads/main/DownloadFtp.php | php -- -s="lk.pl" -l="xx" -p="xx" -f="/public_html"
TOKEN - Twój token z bitbucket który
```
# Parametry Obowiązkowe
    -s="Serwer"
    -l="Login"
    -p="Hasło"

# Parametry Dodatkowe
    -f="from - z jakiego katalogu, domyślnie cały katalog"
    -t="to - do jakiego katalogu ma pobrać, domyślnie download-data...."
    -c="connection - ile plików na raz domyślnie 10"
    -d="download_limit - Ile plików pobrać zanim zanotuje pobranie - domyślnie 5000"

# jak działa
1. Tworzy listę plików do pobrania w pliku txt (ważne, aby nie przerwał)
2. pobiera 5000 plików (parametr d)
3. z listy wszystkich plików usuwa te, które pobrał
4. Pobiera następne pliki z listy. Gdyby z jakiegoś powodu skrypt się przerwał po ponownym uruchomieniu będzie kontynuował pobieranie z listy
