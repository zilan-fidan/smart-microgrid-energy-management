# 5. IoT Sensör Verisi / Zaman Serisi Verinin Nasıl Modellendiği

## Bu Konu Neden Önemli?

Gerçek enerji depolama sistemlerinde veriler, bataryaya bağlı sensörlerden (BMS — Battery Management System, akım/gerilim sensörleri, sıcaklık sensörleri vb.) sürekli akış halinde gelir. Bu veriler, projenizde SOC geçmişini, şarj/deşarj işlemlerini ve varsa piyasa fiyatlarını **zaman damgalı (timestamped)** şekilde saklamanızı gerektiren "zaman serisi (time series)" verilerdir.

Brief'in "Şarj/deşarj işlemleri... bunun zaman damgalı bir geçmiş olarak tutulması" gereksinimi doğrudan bu konuyla ilgilidir.

## Zaman Serisi Verisi Nedir?

Zaman serisi verisi, her kaydın bir **zaman damgasıyla (timestamp)** ilişkilendirildiği veri türüdür. Normal ilişkisel veriden farkı, verinin **sıralı ve zamana bağlı** olmasıdır — geçmiş, şimdi ve gelecek arasındaki ilişki anlamlıdır (örn. "SOC dün %40'tı, bugün %60" gibi trend analizleri yapılabilir).

**Örnek zaman serisi kaydı:**
```json
{
  "timestamp": "2026-07-23T14:30:00Z",
  "storageUnitId": "unit-01",
  "eventType": "charge",
  "amountKwh": 15.5,
  "socBefore": 45.2,
  "socAfter": 60.7
}
```

## IoT Sensör Verisinin Gerçek Dünyadaki Özellikleri

Gerçek bir IoT tabanlı enerji izleme sisteminde şu özellikler bulunur (projenizde bunları basitleştirerek simüle edeceksiniz):

1. **Yüksek frekanslı veri akışı:** Sensörler saniyede birkaç kez veya dakikada bir veri gönderebilir (örn. anlık gerilim, akım, sıcaklık). Sizin projenizde bu kadar sık veri gerekmez — kullanıcı işlem yaptıkça (manuel şarj/deşarj) veya belirli zaman aralıklarında (örn. saatlik) veri üretmeniz yeterlidir.
2. **Eksik/gürültülü veri:** Gerçek sensörler bazen veri kaybedebilir veya hatalı okuma yapabilir. Küçük bir projede bu konuyu göz ardı edebilirsiniz, ama bilginiz olsun diye bahsedilmesi gereken bir gerçek dünya zorluğudur.
3. **Depolama biçimi:** Gerçek IoT sistemlerinde bu veriler genellikle **time-series veritabanlarında** (InfluxDB, TimescaleDB gibi) saklanır çünkü bu veritabanları büyük hacimli, zaman sıralı veriyi verimli sorgulamak için optimize edilmiştir. **Ancak projenizde veritabanı kullanımı yasak** olduğu için, bu veriyi bellekte bir `List<T>` (C#) veya basit bir JSON dosyasında saklayacaksınız — küçük ölçekte bu tamamen yeterlidir.

## Projenizde Zaman Serisi Verisini Nasıl Modellersiniz?

### Basit bir yaklaşım — İşlem Geçmişi (Transaction Log)

Her şarj/deşarj işlemini bir kayıt (event) olarak saklayın:

```csharp
public class StorageTransaction
{
    public Guid Id { get; set; }
    public Guid StorageUnitId { get; set; }
    public DateTime Timestamp { get; set; }
    public TransactionType Type { get; set; } // Charge / Discharge
    public double AmountKwh { get; set; }
    public double SocBefore { get; set; }
    public double SocAfter { get; set; }
}
```

Bu yapı sayesinde:
- Geçmişteki tüm işlemleri kronolojik olarak listeleyebilirsiniz
- SOC'nin zaman içindeki değişimini bir grafikte gösterebilirsiniz (frontend'de çizgi grafik gibi)
- Round-trip verimlilik gibi hesaplamaları geçmiş verilerden türetebilirsiniz

### Zaman Bazlı Sorgulama İhtiyaçları

Bir dashboard'da genellikle şu tür sorular sorulur — bunları düşünerek veri modelinizi tasarlamanız faydalı olur:
- "Son 24 saatteki tüm işlemler nedir?"
- "Bugün toplam kaç kWh şarj edildi, kaç kWh deşarj edildi?"
- "SOC zaman içinde nasıl değişti?" (bir grafik için)

Bu sorguları basitçe bellekteki listeyi zaman damgasına göre filtreleyerek (`.Where(t => t.Timestamp >= DateTime.Now.AddHours(-24))` gibi) cevaplayabilirsiniz — büyük veri altyapısına gerek yoktur.

## Mock/Simüle Veri Üretimi

Projenizde gerçek sensörünüz olmadığı için, zaman serisi verisini **simüle etmeniz** gerekecek:
- Piyasa fiyatı için mock saatlik fiyat listesi (brief'te bahsedilen JSON dosyası)
- Güneş/rüzgar üretimi için mock üretim eğrisi (isteğe bağlı, Konu 4 ile bağlantılı)
- Kullanıcının yaptığı manuel şarj/deşarj işlemleri gerçek zamanlı olarak zaman damgasıyla kaydedilir

## Neden Bu Konu "Araştırma" Gerektiriyor?

Bu başlık, brief'in "derste öğretilmeyen ama işte her gün karşınıza çıkan" becerilere iyi bir örnektir — zaman serisi verisiyle çalışmak (loglama, geçmiş tutma, zaman bazlı filtreleme/agregasyon) hemen hemen her backend projesinde karşınıza çıkar, ancak akademik müfredatta nadiren doğrudan işlenir.

## Özet

| Kavram | Projenizdeki Karşılığı |
|---|---|
| Zaman damgalı kayıt | Her şarj/deşarj işlemi bir `Timestamp` alanıyla saklanır |
| Time-series veritabanı (gerçek dünya) | Sizde: bellekte `List<StorageTransaction>` veya JSON dosyası |
| Sensör veri akışı | Kullanıcı işlemleri + mock/simüle veri (fiyat, üretim) |
| Zaman bazlı sorgulama | LINQ ile `Where(timestamp filtresi)` |
