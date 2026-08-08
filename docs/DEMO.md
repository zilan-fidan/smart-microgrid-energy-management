# Demo Senaryosu

Bu doküman, sistemi baştan sona canlı göstermek için izlenecek adımları anlatır.

## Ön Koşul

Sistem, JSON dosyasında (`storage/app/microgrid.json`) tutulan en az şu varlıklara ihtiyaç duyar:

- 1 güneş santrali
- 1 tüketim noktası
- 1 batarya
- (opsiyonel) 1 rüzgar santrali

`storage/app/microgrid.json` boşsa (`solar_plants`, `wind_plants`, `batteries`, `consumption_points` hepsi `[]`), demoya başlamadan önce `/assets` sayfasından şu örnek değerlerle kayıt ekleyin (saatlik üretim/tüketim profili otomatik oluşturulur, siz sadece kapasite/ortalama giriyorsunuz — bkz. Faz 2):

| Varlık | Alan | Değer |
|---|---|---|
| Güneş | Ad | Çatı GES |
| | Kapasite | 80 kW |
| Rüzgar (opsiyonel) | Ad | Vadi RES |
| | Kapasite | 40 kW |
| Tüketim | Ad | Ana Tesis |
| | Ortalama tüketim | 45 kWh |
| Batarya | Ad | Ana Batarya |
| | Kapasite | 100 kWh |
| | SOC | %50 |
| | Min SOC | %10 |
| | Max SOC | %90 |
| | Verimlilik | 0.9 |

## Adım Adım Demo Akışı

### 1. Varlıkları göster

`/assets` sayfasına gidin. Üstteki sekmelerden (Güneş / Rüzgar / Batarya / Tüketim) her birine tıklayıp az önce eklenen kayıtları gösterin. Batarya sekmesinde tek kayıt kuralına dikkat çekin — sistemde aynı anda birden fazla batarya olamaz, "Sil" dışında ikinci bir "Ekle" formu yok.

### 2. Simülasyonu çalıştır

`/simulation` sayfasına gidin, **"Simülasyonu Başlat"** butonuna basın. Bu bir *"ne olurdu"* simülasyonudur — kayıtlı batarya SOC/SOH'u değişmez, her tıklamada sıfırdan 24 saatlik bir hesap yapılır (bkz. Faz 4 — yan etkisiz simülasyon garantisi).

Sonuç tablosunda ve grafiklerde şunlara dikkat çekin:

- **Gece saatleri (00-05):** Güneş üretimi sıfıra yakın, rüzgar (varsa) düzensiz. Tipik olarak `use_battery` (batarya yeterliyse) veya `draw_from_grid` (batarya azaldıysa) görürsünüz. Her satırın **Gerekçe** sütununda neden o kararın verildiği yazar — örn. *"Üretim açığı: 32.4 kWh"*, *"Batarya SOC yeterli (%45.2 > min %10)"*.
- **Öğlen güneş fazlası (08-16 civarı):** Üretim tüketimi aştığı ve fiyat günün medyanının altında olduğu saatlerde `store` kararı görülür. Gerekçede *"Üretim fazlası: ... kWh"*, *"Fiyat düşük (medyan altı)"*, *"Batarya SOC sınırın altında, depolama mümkün"* ifadeleri yer alır. Miktarın üretim fazlasından biraz düşük olmasına dikkat çekin — round-trip verimlilik kaybı (`√efficiency_rate`) burada devrede.
- **Akşam pahalı saatler (17-21 civarı):** Fiyat piyasa eğrisinde zirveye çıkar. Üretim fazlaysa `sell` (gerekçede *"Fiyat yüksek (medyan üstü)"*), açık varsa ve batarya yeterliyse `use_battery` (gerekçede *"Fiyat yüksek, şebekeden almak yerine batarya tercih edildi"*) görülür — sistem burada şebekeden almak yerine bilinçli olarak bataryayı tercih ediyor.
- **SOH (gün sonu):** Üst kısımdaki "Batarya SOH (gün sonu)" kartına bakın — %100'den hafifçe düşmüş olmalı (örn. %99.9x). Bu, o günkü şarj/deşarj döngülerinin yarattığı yıpranma modelidir (bkz. Faz 6-A), abartılı değil ama gözle görülür.
- **Maliyet/Tasarruf kartları:** "Batarya Olmasaydı (Maliyet)" kartı, batarya hiç olmasaydı o günün net şebeke faturasının ne olacağını gösterir (tüm açık şebekeden alınır, tüm fazla piyasaya satılır — bkz. Faz 6-B baseline tanımı). "Gerçekleşen Net Maliyet" kartı bataryalı sistemin gerçek net nakit akışını gösterir. "Tasarruf" kartı ikisinin farkı — bataryanın o gün için parasal karşılığı budur.

### 3. Guard davranışını canlı göster

SOC sınırlarının hiçbir zaman ihlal edilmediğini göstermek için:

1. `/assets` → Batarya sekmesi → SOC'yi **%90** (max ile aynı) yapıp kaydedin.
2. `/simulation`'a dönüp tekrar çalıştırın. Güneşin bol olduğu bir saatte, normalde `store` beklenirken kararın `sell`'e yönlendiğini ve gerekçede *"Batarya SOC üst sınırda (%90), depolama engellendi"* yazdığını gösterin.
3. Aynı şekilde SOC'yi **%10** (min ile aynı) yapıp tekrar çalıştırın — açık olan bir saatte `use_battery` yerine `draw_from_grid`'e yönlendiğini ve *"Batarya SOC alt sınırda (%10), bataryadan kullanım engellendi"* gerekçesini gösterin.
4. Demo bitince SOC'yi tekrar makul bir değere (örn. %50) döndürmeyi unutmayın.

## Sistemin Ne Yapmadığı

- **SOH karar motoruna geri beslenmiyor.** Batarya yıprandıkça (SOH düştükçe) gerçek kapasitesi azalır, ama karar motoru hâlâ nominal kapasiteyle hesap yapıyor — SOH şu an sadece izleniyor/gösteriliyor.
- **Frekans regülasyonu simüle edilmiyor.** Sistem sadece saatlik enerji dengesi (kWh) üzerinden karar veriyor, şebeke frekansı/gerilim kalitesi gibi anlık (saniye/dakika ölçeğinde) hizmetler modellenmiyor.
- **Piyasa fiyatı ve hava durumu mock veridir.** Gerçek bir gün öncesi piyasa API'sine veya meteorolojik tahmine bağlanmıyor — `MockMarketPriceProvider` ve `Solar/Wind/ConsumptionProfileGenerator` sınıfları gerçekçi ama üretilmiş veri döndürüyor.
- **Eşzamanlı çoklu kullanıcı/kilitleme yok.** `storage/app/microgrid.json` düz bir dosya; aynı anda birden fazla kişi varlık düzenlerse yarış durumu (race condition) oluşabilir.
