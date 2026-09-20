<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Log;

/**
 * TEFAS adapter. Talks to the JSON gateway at www.tefas.gov.tr/api/funds.
 * Errors come back inside a 200 body, so they are read out after each call.
 */
class TefasProvider
{
    public string $name = 'tefas';

    private string $base;
    private HttpClient $http;
    private array $sampled = [];

    private const ENDPOINTS = [
        'catalog' => '/fonYonetimBazliBilgiGetir',
        'founders' => '/fonKurucuGetir',
        'dailyList' => '/fonGnlBlgSiraliGetir',
        'allocation' => '/dagilimSiraliGetirT',
        /** Per-fund künye: ISIN, risk value (1–7), valör days, commissions. */
        'profile' => '/fonProfilBilgiGetir',
    ];

    private const MAPPING = [
        'code' => ['fonKodu', 'fonKod'],
        'name' => ['fonUnvan', 'fonUnvani'],
        'founderCode' => ['kurucuKod', 'kurucuKodu'],
        'founderName' => ['kurucuUnvan', 'kurucuUnvani'],
        'type' => ['fonTurAciklama', 'fonTuru'],
        'typeCode' => ['fonTurKod', 'fonTurKodu'],
        'date' => ['tarih'],
        'price' => ['fiyat'],
        'totalValue' => ['portfoyBuyukluk', 'portBuyukluk'],
        'investors' => ['kisiSayisi', 'yatirimciSayi'],
        'shares' => ['tedPaySayisi', 'payAdet'],
        'managementFee' => ['uygulananYu1Y', 'fonIcTuzukYu1G'],
        'onTefas' => ['tefasDurum'],
    ];

    private const ALLOCATION_LABELS = [
        'hs' => 'Hisse Senedi', 'dt' => 'Devlet Tahvili', 'hb' => 'Hazine Bonosu',
        'fb' => 'Finansman Bonosu', 'ost' => 'Özel Sektör Tahvili', 'bb' => 'Banka Bonosu',
        'vdm' => 'Varlığa Dayalı Menkul Kıymet', 'eut' => 'Eurobond',
        'kibd' => 'Kamu Dış Borçlanma Aracı', 'osdb' => 'Özel Sektör Dış Borçlanma Aracı',
        'kba' => 'Kamu Dövize Endeksli İç Borçlanma Aracı', 'dot' => 'Dövize Ödemeli Bono',
        'db' => 'Dövize Ödemeli Tahvil', 'tpp' => 'Takasbank Para Piyasası',
        'bpp' => 'BİST Para Piyasası', 'btaa' => 'BİST Taahhütlü İşlem Alım',
        'btas' => 'BİST Taahhütlü İşlem Satım', 'r' => 'Repo', 'tr' => 'Ters Repo',
        'vm' => 'Vadeli Mevduat', 'vmtl' => 'Vadeli Mevduat (TL)', 'vmd' => 'Vadeli Mevduat (Döviz)',
        'vmau' => 'Vadeli Mevduat (Altın)', 'kh' => 'Katılma Hesabı', 'khtl' => 'Katılma Hesabı (TL)',
        'khd' => 'Katılma Hesabı (Döviz)', 'khau' => 'Katılma Hesabı (Altın)',
        'kks' => 'Kamu Kira Sertifikası', 'kkstl' => 'Kamu Kira Sertifikası (TL)',
        'kksd' => 'Kamu Kira Sertifikası (Döviz)', 'kksyd' => 'Kamu Yurt Dışı Kira Sertifikası',
        'osks' => 'Özel Sektör Kira Sertifikası', 'oksyd' => 'Özel Sektör Yurt Dışı Kira Sertifikası',
        'km' => 'Kıymetli Maden', 'kmbyf' => 'Kıymetli Maden Borsa Yatırım Fonu',
        'kmkba' => 'Kıymetli Madenler Kamu Borçlanma Aracı', 'kmkks' => 'Kıymetli Madenler Kira Sertifikası',
        'ymk' => 'Yabancı Menkul Kıymet', 'yba' => 'Yabancı Borçlanma Aracı',
        'ybkb' => 'Yabancı Kamu Borçlanma Aracı', 'ybosb' => 'Yabancı Özel Sektör Borçlanma Aracı',
        'yhs' => 'Yabancı Hisse Senedi', 'ybyf' => 'Yabancı Borsa Yatırım Fonu',
        'fkb' => 'Fon Katılma Belgesi', 'yyf' => 'Yatırım Fonu Katılma Payı',
        'byf' => 'Borsa Yatırım Fonu', 'gykb' => 'Gayrimenkul Yatırım Fonu',
        'gyy' => 'Gayrimenkul Yatırım Ortaklığı', 'gsykb' => 'Girişim Sermayesi Yatırım Fonu',
        'gsyy' => 'Girişim Sermayesi Yatırım Ortaklığı', 'gas' => 'Gayrimenkul Sertifikası',
        't' => 'Türev Araç', 'vint' => 'Vadeli İşlem Nakit Teminatı', 'd' => 'Diğer',
    ];

    private const MAX_RANGE_DAYS = 28;
    private const EMPTY_MESSAGES = ['out of bounds', 'veri bulunamadı', 'kayıt bulunamadı'];

    public function __construct()
    {
        $this->base = rtrim(config('ingest.tefas.base_url'), '/');
        $this->http = new HttpClient(
            config('ingest.tefas.rate_limit'),
            config('ingest.tefas.rate_window_ms'),
        );
    }

    private function fundTypes(): array
    {
        $configured = trim((string) config('ingest.tefas.fund_types'));
        if ($configured === '') {
            return Constants::FUND_TYPES;
        }

        $wanted = array_map(fn ($v) => strtoupper(trim($v)), explode(',', $configured));
        $known = array_values(array_filter(Constants::FUND_TYPES, fn ($t) => in_array($t, $wanted, true)));

        if (empty($known)) {
            throw new \RuntimeException('TEFAS_FUND_TYPES has no known type');
        }
        return $known;
    }

    private function call(string $path, array $body): array
    {
        $payload = $this->http->requestJson("{$this->base}{$path}", [
            'body' => $body,
            'timeoutMs' => config('ingest.tefas.timeout_ms'),
            'maxRetries' => config('ingest.tefas.max_retries'),
            'headers' => [
                'accept' => '*/*',
                'origin' => 'https://www.tefas.gov.tr',
                'referer' => 'https://www.tefas.gov.tr/tr/fon-verileri',
                'accept-language' => 'tr-TR,tr;q=0.9',
                'user-agent' => config('ingest.tefas.user_agent'),
            ],
        ]);

        $message = trim((string) ($payload['errorMessage'] ?? ''));
        if ($message !== '' && !$this->isEmptyMessage($message)) {
            throw new UpstreamError("{$path} failed: {$message}");
        }

        $rows = ($message !== '' || empty($payload['resultList'])) ? [] : $payload['resultList'];

        if (config('ingest.log_samples') && count($rows) > 0 && !isset($this->sampled[$path])) {
            $this->sampled[$path] = true;
            Log::info("[tefas] sample row from {$path}: " . json_encode($rows[0]));
        }

        return $rows;
    }

    private function isEmptyMessage(string $message): bool
    {
        $text = mb_strtolower($message, 'UTF-8');
        foreach (self::EMPTY_MESSAGES as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }
        return false;
    }

    private function toTefasDate(string $iso): string
    {
        return str_replace('-', '', $iso);
    }

    private function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime("{$date} +{$days} days"));
    }

    private function splitRange(string $from, string $to): array
    {
        $chunks = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $end = $this->addDays($cursor, self::MAX_RANGE_DAYS - 1);
            $chunks[] = ['from' => $cursor, 'to' => $end < $to ? $end : $to];
            $cursor = $this->addDays($end, 1);
        }
        return $chunks;
    }

    private function listBody(array $range, string $fundType): array
    {
        return [
            'fonTipi' => $fundType, 'fonKodu' => null, 'aramaMetni' => null,
            'fonTurKod' => null, 'fonGrubu' => null, 'sfonTurKod' => null,
            'fonTurAciklama' => null, 'kurucuKod' => null,
            'basTarih' => $this->toTefasDate($range['from']),
            'bitTarih' => $this->toTefasDate($range['to']),
            'basSira' => 1, 'bitSira' => 100000, 'dil' => 'TR',
            'sFonTurKod' => '', 'fonKod' => '', 'fonGrup' => '', 'fonUnvanTip' => '',
        ];
    }

    /** @return array<int, array{fundType:string, rows:array}> */
    private function listings(string $path, array $range): array
    {
        $results = [];
        foreach ($this->fundTypes() as $fundType) {
            foreach ($this->splitRange($range['from'], $range['to']) as $chunk) {
                $results[] = [
                    'fundType' => $fundType,
                    'rows' => $this->call($path, $this->listBody($chunk, $fundType)),
                ];
            }
        }
        return $results;
    }

    private function founderNames(array $types): array
    {
        $names = [];
        foreach ($types as $fonTipi) {
            $rows = $this->call(self::ENDPOINTS['founders'], ['fonTipi' => $fonTipi, 'dil' => 'TR']);
            foreach ($rows as $row) {
                $code = Parse::pick($row, self::MAPPING['founderCode']);
                $name = Parse::pick($row, self::MAPPING['founderName']);
                if (is_string($code) && is_string($name)) {
                    $names[trim($code)] = trim($name);
                }
            }
        }
        return $names;
    }

    private function onTefas(array $row): ?bool
    {
        $value = Parse::pick($row, self::MAPPING['onTefas']);
        return is_bool($value) ? $value : null;
    }

    private function investorCount(array $row, string $fundType): ?int
    {
        $value = Parse::toInteger(Parse::pick($row, self::MAPPING['investors']));
        return ($fundType === 'BYF' && $value === 0) ? null : $value;
    }

    /** @return array<int, array> fund catalogue entries */
    public function listFunds(): array
    {
        $types = $this->fundTypes();
        $founders = $this->founderNames($types);

        $catalogues = [];
        foreach ($types as $fonTipi) {
            $catalogues[] = $this->call(self::ENDPOINTS['catalog'], ['fonTipi' => $fonTipi, 'dil' => 'TR']);
        }

        $seen = [];
        $entries = [];

        foreach ($catalogues as $index => $rows) {
            foreach ($rows as $row) {
                $code = Parse::pick($row, self::MAPPING['code']);
                $name = Parse::pick($row, self::MAPPING['name']);
                if (!is_string($code) || !is_string($name)) {
                    continue;
                }

                $normalised = strtoupper(trim($code));
                if (isset($seen[$normalised])) {
                    continue;
                }
                $seen[$normalised] = true;

                $founderCode = Parse::pick($row, self::MAPPING['founderCode']);
                $founder = (is_string($founderCode) ? ($founders[trim($founderCode)] ?? null) : null) ?? 'Bilinmiyor';

                $entries[] = [
                    'code' => $normalised,
                    'name' => trim($name),
                    'founder' => $founder,
                    'category' => Parse::toCategory(Parse::pick($row, self::MAPPING['type'])),
                    'fundType' => $types[$index],
                    'typeCode' => ((string) (Parse::pick($row, self::MAPPING['typeCode']) ?? '')) ?: null,
                    'managementFee' => Parse::toNumber(Parse::pick($row, self::MAPPING['managementFee'])),
                    'onTefas' => $this->onTefas($row),
                ];
            }
        }

        return $entries;
    }

    /** @return array<int, array> daily stat rows */
    public function fetchDailyStats(array $range): array
    {
        $results = $this->listings(self::ENDPOINTS['dailyList'], $range);
        $stats = [];

        foreach ($results as $result) {
            foreach ($result['rows'] as $row) {
                $code = Parse::pick($row, self::MAPPING['code']);
                $date = Parse::toIsoDate(Parse::pick($row, self::MAPPING['date']));
                $price = Parse::toNumber(Parse::pick($row, self::MAPPING['price']));

                if (!is_string($code) || !$date || $price === null) {
                    continue;
                }

                $stats[] = [
                    'code' => strtoupper(trim($code)),
                    'date' => $date,
                    'price' => $price,
                    'totalValue' => Parse::toNumber(Parse::pick($row, self::MAPPING['totalValue'])),
                    'investorCount' => $this->investorCount($row, $result['fundType']),
                    'shareCount' => Parse::toNumber(Parse::pick($row, self::MAPPING['shares'])),
                ];
            }
        }

        return $stats;
    }

    /**
     * Per-fund künye from TEFAS: ISIN, risk value, and settlement valör days.
     * TEFAS names the fields from the fund's side, so its "geri alış" (buy-back)
     * is the investor's Alış valörü and its "satış" (sale) the Satış valörü.
     * Returns null when TEFAS has no profile for the code.
     */
    public function fetchFundProfile(string $code): ?array
    {
        $rows = $this->call(self::ENDPOINTS['profile'], ['fonKodu' => strtoupper($code), 'dil' => 'TR']);
        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        $isin = Parse::pick($row, ['isinKodu']);

        return [
            'isin' => is_string($isin) && trim($isin) !== '' ? trim($isin) : null,
            'risk' => Parse::toInteger(Parse::pick($row, ['riskDegeri'])),
            'buyValueDays' => Parse::toInteger($row['fonGeriAlisValor'] ?? null),
            'sellValueDays' => Parse::toInteger($row['fonSatisValor'] ?? null),
        ];
    }

    /** @return array<int, array> allocation slices */
    public function fetchAllocations(array $range): array
    {
        $results = $this->listings(self::ENDPOINTS['allocation'], $range);
        $slices = [];

        foreach ($results as $result) {
            foreach ($result['rows'] as $row) {
                $code = Parse::pick($row, self::MAPPING['code']);
                $date = Parse::toIsoDate(Parse::pick($row, self::MAPPING['date']));
                if (!is_string($code) || !$date) {
                    continue;
                }

                foreach (self::ALLOCATION_LABELS as $key => $label) {
                    $pct = Parse::toNumber($row[$key] ?? null);
                    if ($pct === null || $pct === 0.0) {
                        continue;
                    }
                    $slices[] = [
                        'code' => strtoupper(trim($code)),
                        'date' => $date,
                        'label' => $label,
                        'pct' => $pct,
                    ];
                }
            }
        }

        return $slices;
    }
}
