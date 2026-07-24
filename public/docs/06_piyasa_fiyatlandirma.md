# 6. Enerji Piyasası Fiyatlandırma Mantığı

## Elektrik Fiyatı Neden Sabit Değildir?

Elektrik, büyük ölçekte **depolanamayan** (geleneksel olarak) bir emtiadır — üretildiği anda tüketilmesi gerekir. Bu yüzden elektrik fiyatı, o anki **arz-talep dengesine** göre sürekli değişir: talep yüksek/arz düşükken fiyat yükselir, talep düşük/arz (özellikle yenilenebilir) yüksekken fiyat düşer, hatta bazen negatife bile inebilir (üretimi durduramayan santraller, şebekeye enerji vermek için para ödemeyi tercih edebilir).

## Gün Öncesi Piyasası (Day-Ahead Market)

Çoğu ülkede elektrik ticaretinin merkezinde **gün öncesi piyasası (day-ahead market)** bulunur:

- Piyasa katılımcıları (üreticiler, tedarikçiler), bir sonraki günün her saati için ne kadar üretim/tüketim yapacaklarını önceden bildirirler.
- Bu teklifler bir araya getirilir ve her saat için **tek bir denge fiyatı (clearing price)** oluşur — bu, arz ve talep eğrilerinin kesiştiği noktadır (ekonomideki klasik arz-talep dengesi mantığı).
- Sonuç olarak, bir sonraki günün 24 saati için saatlik fiyat listesi ortaya çıkar (bazı piyasalarda 15 dakikalık dilimler de vardır).

**Türkiye'de durum (brief'te belirtildiği gibi):** Türkiye'de gün öncesi piyasası (**GÖP — EPİAŞ tarafından işletilir**) ülke genelinde **tek bir fiyat** üzerinden çalışır — yani bölgesel/zonal fiyat farkı yoktur, tüm Türkiye için aynı saatte aynı fiyat geçerlidir. Bu, projenizin fiyat modelini basitleştirir: bölgeye göre farklı fiyat tanımlamanıza gerek yoktur, sadece **saate göre değişen tek bir fiyat listesi** yeterlidir.

## Bölgesel/Zonal Fiyatlandırma (Genel Bilgi)

Bazı ülkelerde (örn. ABD'nin bazı eyaletleri, İskandinav ülkeleri — Nordic Nord Pool sistemi) şebekenin farklı bölgelerinde farklı fiyatlar oluşabilir. Bunun sebebi, iletim hatlarının kapasite kısıtları nedeniyle bir bölgeden diğerine sınırsız enerji taşınamamasıdır — bu da bölgeler arasında fiyat farkına yol açar ("congestion pricing" / tıkanıklık fiyatlandırması). Türkiye'de bu proje kapsamında bu konuyu düşünmenize gerek yok, ama genel kültür olarak bilinmesi faydalıdır.

## Anlık Denge Piyasası (Balancing/Real-Time Market)

Gün öncesi piyasasının yanında, gerçek zamanlı arz-talep dengesizliklerini gidermek için **dengeleme (balancing) piyasası** da vardır — bu piyasa, gerçek üretim/tüketimin gün öncesi tahminlerden sapması durumunda devreye girer ve genellikle çok daha oynak (volatile) fiyatlarla çalışır. Bu, projenizin kapsamı dışındadır ama "gerçek zamanlı olması gerekmiyor" notunun (brief'te belirtilen) neden verildiğini anlamanıza yardımcı olur — sizden istenen basitleştirilmiş gün öncesi fiyat mantığıdır, anlık dengeleme piyasası değil.

## Fiyatların Tipik Günlük Deseni

Genel bir kural olarak (ülkeden ülkeye değişse de), tipik bir günlük fiyat eğrisi şöyle bir patern izler:

| Saat aralığı | Genel eğilim | Sebep |
|---|---|---|
| Gece (00:00–06:00) | Düşük fiyat | Talep düşük, genelde rüzgar üretimi güçlü olabilir |
| Sabah (07:00–09:00) | Yükseliş | İnsanlar uyanıyor, işyerleri açılıyor |
| Öğlen (10:00–15:00) | Düşebilir | Güneş üretimi zirvede (güneşli günlerde) |
| Akşam (17:00–21:00) | En yüksek | Talep zirvesi, güneş üretimi azalıyor/bitiyor |
| Gece yarısına doğru | Tekrar düşüş | Talep azalıyor |

Bu genel patern, projenizde mock fiyat verisi oluştururken gerçekçi bir başlangıç noktası olabilir.

## Projenizde Mock Fiyat Verisi Nasıl Oluşturulur?

Brief'te belirtildiği gibi gerçek bir API'ye bağlanmanıza gerek yoktur. Basit bir yaklaşım:

```json
{
  "date": "2026-07-23",
  "hourlyPrices": [
    { "hour": 0, "priceTlPerMwh": 1450 },
    { "hour": 1, "priceTlPerMwh": 1400 },
    ...
    { "hour": 18, "priceTlPerMwh": 2800 },
    { "hour": 19, "priceTlPerMwh": 3100 },
    ...
    { "hour": 23, "priceTlPerMwh": 1600 }
  ]
}
```

Bu veriyi elle oluşturabilir (yukarıdaki tabloyu referans alarak) veya basit bir formülle (örn. bir sinüs dalgası + rastgele gürültü) kod içinde üretebilirsiniz.

## Gerçek Veri Kaynakları (Bilgi Amaçlı — Bağlanmanız Gerekmiyor)

Eğer gerçekçi bir fiyat deseni görmek isterseniz (kopyalamak için değil, ilham almak için), Türkiye'deki gün öncesi piyasası fiyatları **EPİAŞ Şeffaflık Platformu** üzerinden herkese açık şekilde yayınlanır. Ancak brief'in de belirttiği gibi projenizde gerçek bir API entegrasyonu **zorunlu değildir** — mock veri yeterlidir.

## Neden Bu Konu Trading Kararı İçin Temel?

Konu 7'de ele alınacak trading/arbitraj mantığı, doğrudan bu fiyat verisi üzerine kuruludur: "fiyat düşükse depola, yüksekse sat" kuralı yazabilmeniz için önce fiyatın **neden ve nasıl** değiştiğini anlamanız, kararınızı mantıklı bir zemine oturtmanızı sağlar.

## Özet

| Kavram | Anlamı | Projeye Etkisi |
|---|---|---|
| Gün öncesi piyasası | Bir sonraki günün saatlik fiyatlarının önceden belirlenmesi | Mock saatlik fiyat listesi mantığının temeli |
| Tek fiyat (Türkiye) | Bölgesel fark yok, tüm ülke aynı saatte aynı fiyat | Fiyat modelini basitleştirir |
| Dengeleme piyasası | Anlık arz-talep sapmalarını giderir | Projenizin kapsamı dışında |
| Günlük fiyat paterni | Gece düşük, akşam yüksek (genel eğilim) | Mock veri oluştururken referans alınabilir |
