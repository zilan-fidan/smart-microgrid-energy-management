# 4. Güneş/Rüzgar Gibi Yenilenebilir Kaynaklarla Depolamanın Birlikte Çalışması

## Neden Yenilenebilir Enerji ile Depolama Birlikte Anılır?

Güneş ve rüzgar enerjisinin en büyük dezavantajı **değişkenlik (intermittency)**'dir — üretim, hava koşullarına ve güneşin/rüzgarın doğal döngüsüne bağlıdır, talebe göre ayarlanamaz. Depolama sistemleri bu değişkenliği yönetmenin en etkili yollarından biridir; üretim ile tüketim arasındaki zamansal uyumsuzluğu gidermeye yardımcı olurlar.

## Güneş Enerjisi ve Depolama

Güneş panelleri sadece gündüz, güneş varken üretim yapar. Ancak elektrik talebi genellikle **akşam** saatlerinde zirve yapar (insanlar eve döner, aydınlatma-klima-ısıtma kullanılır). Bu durum, enerji sektöründe **"Duck Curve" (Ördek Eğrisi)** olarak bilinen ünlü bir fenomene yol açar:

- Gün ortasında güneş üretimi çok yüksektir, net şebeke talebi (talep − güneş üretimi) çok düşer, hatta bazı bölgelerde negatife yaklaşır.
- Güneş battıkça (akşamüstü) üretim hızla düşer ama talep hâlâ yüksektir veya artmaya başlar — bu ani geçiş şebeke için zorlayıcıdır ("ördek boynu").

**Depolamanın rolü:** Gündüz fazla üretilen güneş enerjisi depoya alınır, akşam talep arttığında bu enerji şebekeye/tüketiciye geri verilir. Bu, brief'te bahsedilen "üretilen enerjiyi şu an depola mı, piyasaya sat mı" kararının temelini oluşturur.

## Rüzgar Enerjisi ve Depolama

Rüzgar enerjisi güneşten farklı olarak günlük bir döngüye sahip değildir — rüzgar günün herhangi bir saatinde esebilir veya esmeyebilir, bu da tahmin edilebilirliği daha da zorlaştırır. Rüzgar üretimi genellikle:
- Gece saatlerinde (talebin düşük olduğu zamanlarda) daha güçlü olabilir
- Hava durumuna bağlı olarak saatler/günler boyunca dalgalanabilir

**Depolamanın rolü:** Rüzgarın en çok estiği ama talebin düşük olduğu saatlerde (örn. gece) üretilen fazla enerji depolanır, rüzgarın zayıf olduğu ama talebin yüksek olduğu saatlerde bu enerji kullanılır.

## Temel Kavram: "Zaman Kaydırma" (Time-Shifting)

Yenilenebilir + depolama entegrasyonunun özünde yatan fikir, üretim zamanı ile tüketim zamanı arasındaki uyumsuzluğu **zamanda kaydırarak** gidermektir:

```
Üretim anı ≠ Tüketim anı  →  Depolama bu ikisini "zaman içinde eşleştiren" bir köprüdür
```

## Bu Entegrasyonu Projenizde Nasıl Modellersiniz?

Brief'in önerdiği "İleri Seviye Fikir" (trading/karar destek) tam olarak bu mantığı basitleştirilmiş şekilde uygular. Basit bir model şöyle kurulabilir:

1. **Mock üretim verisi** oluşturun — örneğin saatlik bir "güneş üretim profili" (gündüz saatlerinde yüksek, gece sıfır — basit bir sinüs eğrisi veya sabit tablo ile simüle edilebilir).
2. **Mock talep/fiyat verisi** oluşturun — talep yüksekken fiyat da yüksek olur genellikle (brief'teki piyasa fiyatı verisiyle bağlantılı).
3. **Karar mekanizması:**
   - Üretim > anlık ihtiyaç VE SOC henüz max sınırına ulaşmadıysa → **depola**
   - Üretim < anlık ihtiyaç VE SOC min sınırının üzerindeyse → **depodan kullan**
   - Üretim de yeterli, depo da dolu → **şebekeye/piyasaya sat**

## Gerçek Dünyadan Örnek Kullanım Şekilleri

- **Hibrit güneş+batarya santralleri:** Artık birçok yeni güneş santrali, doğrudan yanına batarya ekleyerek kuruluyor (bu tesislere "solar+storage" veya "hibrit" tesis deniyor).
- **Sanal Santral (Virtual Power Plant — VPP):** Birçok küçük ev bataryası, yazılım üzerinden birleştirilip tek bir büyük santral gibi yönetilebilir. Bu, projenizin "birden fazla depolama birimi" opsiyonel genişlemesiyle ilişkilendirilebilir.
- **Kapasite faktörü (capacity factor) iyileştirme:** Depolama, yenilenebilir bir santralin "her zaman belirli bir güç sağlayabilme" garantisini artırır, bu da santralin şebekeye daha güvenilir bir kaynak olarak görünmesini sağlar.

## Neden Bu Konu Projenizin Trading Özelliği İçin Önemli?

Brief'te önerilen ileri seviye özellik şu varsayıma dayanır: *"Sisteminiz bir yenilenebilir enerji kaynağına bağlı."* Bu bağlamı anlamadan trading mantığını kurmak, kararların **neden** verildiğini açıklamayı zorlaştırır. Bu konuyu anladıktan sonra, demo sunumunuzda "neden depoluyoruz / neden satıyoruz" sorusuna güçlü bir cevabınız olur: çünkü üretim ile talep zamanda uyuşmuyor, biz bu farkı depolama ile kapatıyoruz.

## Özet

| Kaynak | Üretim Paterni | Ana Zorluk | Depolamanın Rolü |
|---|---|---|---|
| Güneş | Günlük, öngörülebilir (gündüz) | Akşam talep zirvesiyle çakışmama | Gündüz fazlasını akşama taşımak |
| Rüzgar | Düzensiz, hava bağımlı | Tahmin edilemezlik | Üretim fazlasını talep anına taşımak |
