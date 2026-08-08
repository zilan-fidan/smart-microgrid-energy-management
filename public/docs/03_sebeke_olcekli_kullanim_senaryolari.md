# 3. Şebeke Ölçekli Enerji Depolamanın Kullanım Senaryoları

## Şebeke Ölçekli Depolama Nedir?

"Şebeke ölçekli (grid-scale / utility-scale)" depolama, evsel kullanım (örn. bir evin çatı güneş paneli + küçük batarya seti) yerine, elektrik dağıtım/iletim şebekesine doğrudan bağlı, genellikle **MW (megawatt)** ölçeğinde güce sahip büyük depolama tesislerini ifade eder. Bir ev bataryası birkaç kWh–birkaç düzine kWh iken, şebeke ölçekli bir tesis onlarca–yüzlerce MWh olabilir.

Ancak bu proje kapsamında "şebeke ölçekli" ile "ev tipi" arasındaki fark aslında **kullanım senaryosu ve amaç** farkıdır — teknik olarak modelleme mantığı (SOC, kapasite, güç) aynıdır, sadece sayısal büyüklükler ve kullanım amacı değişir. Brief'te de belirtildiği gibi, hangi senaryoyu seçeceğiniz (ev tipi mi, şebeke ölçekli mi) size kalmış bir tercihtir.

## Şebeke Ölçekli Depolamanın Başlıca Kullanım Senaryoları

### 1. Talep Tepe Noktalarını Yumuşatma (Peak Shaving)

Elektrik talebi gün içinde sabit değildir — genellikle akşam saatlerinde (insanlar eve gelip klima/ısıtıcı/aydınlatma kullandığında) büyük bir **talep tepesi (demand peak)** oluşur. Bu tepe noktalarını karşılamak için şebekenin ekstra kapasiteye ihtiyacı vardır, ancak bu kapasiteyi sadece birkaç saatlik tepe için inşa etmek pahalıdır.

**Çözüm:** Talep düşükken (örn. gece) depoya enerji doldurulur, talep tepe yaptığında (örn. akşam 18:00-21:00) depodan enerji çekilerek şebekenin yükü hafifletilir. Böylece şebeke, tepe talebi karşılamak için aşırı büyük santral/hat kapasitesi kurmak zorunda kalmaz.

*Projeye yansıması:* Sisteminizde saatlik bir "talep profili" simüle edip, belirli saatlerde otomatik deşarj öneren bir kural yazabilirsiniz.

### 2. Frekans Regülasyonu (Frequency Regulation)

Elektrik şebekeleri belirli bir frekansta çalışır (Türkiye ve Avrupa'da 50 Hz). Üretim ile tüketim arasında anlık dengesizlik olduğunda frekans bu değerden sapar (üretim fazlaysa frekans yükselir, talep fazlaysa düşer). Şebeke operatörlerinin bu frekansı dar bir bantta tutması gerekir.

Bataryalar, geleneksel santrallere göre **çok hızlı** tepki verebildiği için (milisaniyeler-saniyeler mertebesinde şarj/deşarj yönü değiştirebilirler) frekans regülasyonunda çok değerlidir. Frekans yükseldiğinde batarya hızlıca şarj olarak fazla enerjiyi emer; frekans düştüğünde hızlıca deşarj olarak destek verir.

*Projeye yansıması:* Bu senaryo, gerçek zamanlı çok hızlı tepki gerektirdiği için stajyer projesinde birebir simüle etmek zordur; ancak "araştırdım, bu bir kullanım senaryosu, sistemim bunu şu şekilde basitleştirerek temsil ediyor" şeklinde bahsedebilirsiniz (örn. basit bir "hızlı tepki modu" kavramı).

### 3. Enerji Arbitrajı (Energy Arbitrage / Trading)

Elektrik fiyatları gün içinde (ve bazı piyasalarda saatlik olarak) değişir — talep düşükken/yenilenebilir üretim boldayken fiyat düşer, talep yüksekken fiyat yükselir. Depolama sistemi, fiyat düşükken enerji alıp depolar, fiyat yüksekken satarak kâr elde eder.

Bu, brief'in "İleri Seviye Fikir" olarak önerdiği trading/karar destek özelliğiyle doğrudan bağlantılıdır (bkz. Konu 7 — Trading Stratejileri dosyası).

### 4. Yenilenebilir Enerji Entegrasyonu ve Üretim Kaydırma (Renewable Firming / Time-Shifting)

Güneş ve rüzgar üretimi değişkendir (güneş sadece gündüz, rüzgar ise düzensiz üretir). Depolama, gündüz fazla üretilen güneş enerjisini depolayıp akşam/gece talebi karşılamak için kullanılabilir — böylece değişken üretim, daha öngörülebilir bir arz profiline "dönüştürülür". Bu konu, Konu 4 dosyasında daha detaylı ele alınıyor.

### 5. Şebeke Yatırımlarını Erteleme (Transmission & Distribution Deferral)

Bir bölgede talep artışı olduğunda normalde yeni hat/trafo yatırımı gerekir. Yerel bir batarya sistemi kurarak bu yatırımı yıllarca erteleyebilirsiniz — çünkü tepe talebi batarya ile karşılanabiliyorsa, şebeke altyapısını büyütmeye gerek kalmaz. Bu daha çok şebeke işletmecisi (dağıtım şirketi) perspektifinden bir kullanım senaryosudur.

### 6. Kesintisiz Güç / Yedekleme (Backup Power / Black Start)

Şebeke kesintisi durumunda depolama sistemi kritik yükleri (hastane, veri merkezi, vb.) besleyebilir, hatta bazı büyük sistemler şebekenin çökmesi durumunda yeniden başlatılmasına (black start) yardımcı olabilir.

## Bu Senaryolardan Hangisini Seçmelisiniz?

Brief'in önerdiği gibi, **★ önerilen senaryo** enerji arbitrajı/trading'dir çünkü:
- Somut, hesaplanabilir bir iş mantığı üretir (fiyat + SOC → karar)
- Mock veri ile kolayca simüle edilebilir (gerçek zamanlı şebeke frekans verisi gibi karmaşık altyapı gerektirmez)
- Hem araştırma hem iş mantığı açısından projeyi "bir üst seviyeye" taşır

Ancak peak shaving da benzer şekilde uygulanabilir bir senaryodur (saatlik talep profili + eşik tabanlı deşarj kararı).

## Özet Tablo

| Senaryo | Ana Fikir | Proje için uygunluk |
|---|---|---|
| Peak shaving | Düşük talepte doldur, tepe talepte boşalt | Kolay simüle edilir |
| Frekans regülasyonu | Anlık şebeke dengesini korumak | Gerçek zamanlılık gerektirir, zor |
| Enerji arbitrajı | Ucuzken al, pahalıyken sat | ★ Önerilen, kolay simüle edilir |
| Yenilenebilir entegrasyonu | Üretim zamanlamasını kaydırma | Orta zorlukta, iyi bir seçenek |
| Şebeke yatırımı erteleme | Uzun vadeli altyapı tasarrufu | Simülasyona uygun değil (uzun vadeli) |
| Yedek güç | Kesinti anında destek | Basit bir "acil durum modu" olarak eklenebilir |
