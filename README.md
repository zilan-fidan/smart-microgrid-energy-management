# Smart Microgrid Energy Management

Güneş + rüzgar santrali, batarya ve tüketim noktasından oluşan bir mikro şebekeyi yöneten, **açıklanabilir** karar destek sistemi. Her saat için 4 dallı bir karar motoru (bataryaya depola / piyasaya sat / bataryadan kullan / şebekeden al) çalışır ve her kararını gerekçeleriyle açıklar. 24 saatlik simülasyon Livewire ile canlı ilerler, sonuçlar Chart.js grafikleriyle gösterilir.

Staj projesi — Laravel 12, Livewire 3, Tailwind CSS, Chart.js.

## Mimari Notlar

- **Veri deposu:** Eloquent/migration yok — tüm domain verisi (varlıklar, simülasyon) `storage/app/microgrid.json` dosyasında tutulur. SQLite yalnızca Laravel'in kendi iç işleyişi (session/cache/queue) için mevcuttur, uygulamanın kendi verisiyle ilgisi yoktur.
- **SOLID:** Karar motoru (Chain of Responsibility — her karar dalı ayrı bir `DecisionRuleInterface` uygulaması), mock veri üretimi, JSON depolama ve simülasyon akışı ayrı servis katmanlarına bölünmüştür. Detaylar için `app/Domain` ve `app/Services` altındaki sınıflara bakın.
- **Yan etkisiz simülasyon:** "Simülasyonu Başlat" bir *"ne olurdu"* hesabıdır — kayıtlı batarya SOC/SOH'unu değiştirmez, her çalıştırma bağımsızdır.
- **Dashboard özeti:** Ana sayfada özet metrik şeridi (Batarya SOC, kayıtlı varlık sayısı, son simülasyon zamanı, son tasarruf) ve son çalıştırılan simülasyonun mini grafikleri (SOC eğrisi + karar dağılımı) gösterilir. Bu veri session'da tutulur, kalıcı JSON depoya yazılmaz — yan etkisiz simülasyon garantisi böylece korunur.
- **Depolama kararı (ekonomik breakeven):** `StoreSurplusRule` fiyatın medyana göre düşük olup olmadığına değil, gerçek bir kâr eşiğine bakar: depola kararı ancak `(beklenen satış fiyatı × verim − alış fiyatı) > çevrim başına aşınma maliyeti (TL/kWh)` sağlanırsa verilir. Yani hem round-trip verimlilik kaybı hem batarya yıpranma maliyeti (`replacementCostTl`'den amortize edilir, bkz. `DegradationCostCalculator`) hesaba katılır — küçük fiyat farkları için artık batarya yorulmuyor. Store kararlarında beklenen net kâr (TL) gerekçede ve simülasyon panelinde ayrı bir kolonda gösterilir.

Uçtan uca bir demo akışı için [docs/DEMO.md](docs/DEMO.md) dosyasına bakın.

## Kurulum

Gereksinimler: PHP 8.2+, Composer, Node.js + npm.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Laravel'in kendi iç tabloları için (session/cache/queue) — uygulama verisiyle ilgisi yok
touch database/database.sqlite
php artisan migrate
```

Uygulama verisinin tutulacağı JSON dosyası yoksa oluşturun:

```bash
# storage/app/microgrid.json yoksa:
cat > storage/app/microgrid.json <<'EOF'
{
    "solar_plants": [],
    "wind_plants": [],
    "batteries": [],
    "consumption_points": [],
    "simulation": null
}
EOF
```

## Çalıştırma

Frontend varlıklarını derleyin (Tailwind + Chart.js entegrasyonu için gerekli):

```bash
npm run build
# ya da geliştirme sırasında canlı yeniden derleme için:
npm run dev
```

Ayrı bir terminalde uygulama sunucusunu başlatın:

```bash
php artisan serve
```

Tarayıcıda:

- `/` — Dashboard (giriş sayfası): özet metrik şeridi + son simülasyonun mini grafikleri (varsa)
- `/assets` — Varlık yönetimi (güneş, rüzgar, batarya, tüketim — CRUD). Batarya formunda "Değiştirme Maliyeti" (TL) alanı bulunur; sistem kapasiteye göre bir öneri değeriyle gelir, siz değiştirebilirsiniz.
- `/simulation` — 24 saatlik simülasyonu çalıştır, sonuç tablosu (Store kararlarında "Beklenen Kâr" kolonu dahil) + grafikleri (SOC/üretim/tüketim, fiyat, saatlik SOH aşınması) gör

İlk çalıştırmada `/assets`'ten en az bir güneş santrali, bir tüketim noktası ve bir batarya eklemeniz gerekir — somut örnek değerler için [docs/DEMO.md](docs/DEMO.md#ön-koşul) dosyasına bakın.

## Testler

```bash
php artisan test
```

55 test. Karar kuralları (breakeven eşiği dahil), verimlilik hesapları, aşınma maliyeti hesabı (`DegradationCostCalculator`), baseline maliyet karşılaştırması ve simülasyonun yan etkisiz çalıştığı `tests/Unit` altında otomatik testlerle doğrulanır. `tests/Feature/Livewire` altında `SimulationPanelTest` ve `DashboardTest` (özet grafiklerin sadece bir simülasyon çalıştıktan sonra göründüğünü doğrular) yer alır.
