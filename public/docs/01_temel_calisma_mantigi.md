# 1. Enerji Depolama Sistemlerinin Temel Çalışma Mantığı

## Genel Bakış

Enerji depolama sistemi (Energy Storage System — ESS), üretilen elektrik enerjisini daha sonra kullanmak üzere saklayan ve ihtiyaç anında geri veren bir sistemdir. En yaygın türü **batarya bazlı depolama (BESS — Battery Energy Storage System)** olduğu için bu proje kapsamında asıl odak burasıdır.

Temel döngü şu şekilde işler:

```
Enerji üretimi/şebeke → [Şarj] → Batarya (enerji saklanır) → [Deşarj] → Tüketim/şebeke
```

## Batarya Bazlı Sistemlerin Çalışma Mantığı

Bir bataryanın içinde kimyasal reaksiyonlar yoluyla elektrik enerjisi depolanır (genellikle lityum-iyon hücreler, şebeke ölçekli sistemlerde). Şarj sırasında dışarıdan verilen elektrik enerjisi kimyasal enerjiye dönüştürülür; deşarj sırasında bu süreç tersine döner.

Bir enerji depolama sistemini yazılım tarafında modellemek için bilmeniz gereken üç temel bileşen vardır:

1. **Kapasite (Capacity)** — Bataryanın toplam ne kadar enerji tutabildiği. Genellikle **kWh (kilowatt-saat)** cinsinden ifade edilir. Örneğin 100 kWh kapasiteli bir batarya, teorik olarak 100 kW gücü 1 saat boyunca sağlayabilir (ya da 50 kW'ı 2 saat, vb.)
2. **Güç (Power)** — Bataryanın aynı anda ne kadar hızlı şarj/deşarj olabildiği. **kW (kilowatt)** cinsinden ifade edilir. Kapasite "ne kadar su tutuyor", güç ise "musluktan ne kadar hızlı akıyor" gibi düşünülebilir.
3. **C-Rate** — Güç ile kapasite arasındaki ilişkiyi ifade eden bir katsayıdır (Güç ÷ Kapasite). Örneğin 100 kWh kapasiteli, 50 kW güce sahip bir sistem 0.5C ile çalışır — yani tam dolu bataryayı boşaltmak 2 saat sürer.

## SOC (State of Charge) — Şarj Durumu

**SOC**, bataryanın o an ne kadar dolu olduğunu gösteren, genellikle **yüzde (%)** cinsinden ifade edilen bir değerdir.

```
SOC (%) = (Mevcut depolanan enerji / Toplam kapasite) × 100
```

Örnek: 100 kWh kapasiteli bir bataryada 60 kWh enerji varsa, SOC = %60'tır.

SOC, bir arabanın yakıt göstergesi gibi düşünülebilir — anlık durumu gösterir, geçmişi değil.

### SOC ile ilgili önemli kurallar (proje için kritik):

- SOC **0% ile 100%** arasında olmak zorundadır — bu matematiksel bir sınırdır.
- Ancak gerçek sistemlerde bataryanın ömrünü korumak için genellikle **güvenli bir aralık** tanımlanır (örneğin %10–%90). Bataryayı sürekli %0'a kadar boşaltmak veya %100'e kadar doldurmak, bataryanın kimyasal ömrünü hızla tüketir.
- Bu yüzden projenizde "min/max güvenli sınır" kavramı önemlidir: SOC bu sınırların dışına çıkarsa sistem uyarı vermeli veya işlemi engellemelidir (brief'teki "aşırı şarj/deşarj engelleme" kuralı tam olarak budur).

## SOH (State of Health) — Bataryanın Sağlık Durumu

SOC ile karıştırılmaması gereken bir başka kavram **SOH**'tur. SOC "şu an ne kadar dolu" sorusuna cevap verirken, SOH "batarya ne kadar yıprandı / orijinal kapasitesinin ne kadarını hâlâ tutabiliyor" sorusuna cevap verir.

```
SOH (%) = (Bataryanın şu anki maksimum kapasitesi / Fabrika çıkışı orijinal kapasite) × 100
```

Bir batarya zamanla ve kullanıldıkça (şarj-deşarj döngü sayısı arttıkça) kapasitesini kaybeder. Örneğin 3 yıl kullanılmış bir batarya artık 100 kWh değil, 92 kWh tutabiliyorsa SOH = %92'dir.

**Bu proje için not:** SOH, brief'te zorunlu bir gereklilik değildir çünkü uzun vadeli döngü verisi ve yaşlanma modellemesi gerektirir — küçük bir stajyer projesinde gerçekçi şekilde simüle etmek zordur. Ancak "ek özellik" olarak eklemek isterseniz (örneğin her N döngüde SOH'u yapay olarak biraz azaltan basit bir kural), sisteminizi daha gerçekçi gösterebilir.

## Bu Kavramların Veri Modeline Yansıması

Projenizde bir `StorageUnit` (Depolama Birimi) sınıfı tasarlarken muhtemelen ihtiyaç duyacağınız alanlar şunlardır:

| Alan | Açıklama | Birim |
|---|---|---|
| `CapacityKwh` | Toplam kapasite | kWh |
| `CurrentChargeKwh` veya `SocPercent` | Anlık şarj durumu | kWh veya % |
| `MaxPowerKw` | Maksimum şarj/deşarj gücü | kW |
| `MinSocPercent` / `MaxSocPercent` | Güvenli çalışma sınırları | % |
| `SohPercent` (opsiyonel) | Sağlık durumu | % |

## Neden Bu Kavramları Anlamak Önemli?

Brief'te belirtilen "en az bir anlamlı iş mantığı kuralı" gereksinimi, büyük ölçüde bu kavramlar üzerine kuruludur:
- Aşırı şarj/deşarj engelleme kuralı → SOC sınırlarını bilmeden yazılamaz
- Düşük/yüksek SOC uyarısı → SOC hesaplamasının doğru yapılmasını gerektirir
- Round-trip verimlilik → şarj ve deşarj miktarları arasındaki farkın SOC üzerinden hesaplanmasını gerektirir

Yani bu ilk konu, projenin geri kalan tüm iş mantığının üzerine oturduğu temel kavram setidir.

## Ek Okuma Yönü (kendi araştırmanız için)

- "Battery Management System (BMS)" — gerçek sistemlerde SOC/SOH hesaplamasını yapan donanım/yazılım katmanı (projenizde bunu basitleştirilmiş şekilde siz simüle edeceksiniz)
- "Depth of Discharge (DoD)" — SOC'nin tamamlayıcısı: DoD = 100% − SOC
