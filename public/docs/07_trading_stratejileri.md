# 7. Basit Alım-Satım (Trading) Stratejilerinin Temelleri — Enerji Arbitrajı

## Enerji Arbitrajı Nedir?

**Arbitraj (arbitrage)**, genel ekonomik anlamda, bir varlığı ucuz olduğu yerden/zamandan alıp pahalı olduğu yerde/zamanda satarak risksiz (veya düşük riskli) kâr elde etme stratejisidir. **Enerji arbitrajı** ise bu mantığın elektrik piyasasına uygulanmış hâlidir:

```
Fiyat düşükken enerji satın al / depola → Fiyat yükselince depodakini sat
```

Bu, brief'in "İleri Seviye Fikir" olarak önerdiği ve ★ ile işaretlediği özelliğin tam olarak dayandığı stratejidir.

## Neden Bu Mümkün?

Konu 6'da anlatıldığı gibi, elektrik fiyatı gün içinde saatlik olarak değişir (gece düşük, akşam yüksek gibi). Bir depolama sistemi, bu fiyat farkından yararlanarak "zamanda alım-satım" yapabilir — tıpkı bir borsa yatırımcısının "düşükten al, yüksekten sat" mantığı gibi, ama burada "varlık" elektrik enerjisidir ve "depo" bir borsa hesabı yerine bir bataryadır.

## Basit Karar Mekanizmasının Bileşenleri

Brief'te belirtilen basit trading mekanizması üç girdiye dayanır:

1. **O anki piyasa fiyatı** (Konu 6'daki mock fiyat listesinden)
2. **Mevcut SOC** (Konu 1'deki şarj durumu)
3. **Önceden tanımlanmış eşik değerler** (fiyat eşiği, SOC eşiği)

Ve üç olası karar üretir: **"depola" / "sat" / "bekle"**

## Basit Kural Tabanlı (Rule-Based) Strateji — En Kolay Yaklaşım

En basit ve anlaşılır yaklaşım, sabit eşik değerlerine dayalı bir karar ağacıdır:

```
EĞER fiyat < DÜŞÜK_EŞİK VE SOC < MAX_SOC:
    → "DEPOLA" (enerji ucuz, depoya doldur)

EĞER fiyat > YÜKSEK_EŞİK VE SOC > MIN_SOC:
    → "SAT" (enerji pahalı, depodakini sat)

AKSİ HALDE:
    → "BEKLE" (ne çok ucuz ne çok pahalı, ya da depo zaten dolu/boş)
```

**Örnek eşik değerleri:** Düşük eşik = günlük ortalama fiyatın altı, Yüksek eşik = günlük ortalama fiyatın üstü. Bunları sabit sayı olarak da tanımlayabilir (örn. "1800 TL/MWh altı ucuz, 2500 TL/MWh üstü pahalı") veya günün ortalama/medyan fiyatına göre dinamik olarak hesaplayabilirsiniz (daha gelişmiş bir yaklaşım).

## Biraz Daha Gelişmiş Yaklaşım: Göreceli Eşik (Percentile-Based)

Sabit sayılar yerine, günün fiyat dağılımına göre karar vermek daha esnek bir yöntemdir:

```
Günün en düşük %25'lik fiyat dilimindeyse → depola
Günün en yüksek %25'lik fiyat dilimindeyse → sat
```

Bu yaklaşım, farklı günlerde fiyat seviyeleri değişse bile (örn. bir gün genel olarak daha pahalıysa) mantığın hâlâ anlamlı kararlar üretmesini sağlar.

## Neden Bu Bir "Öngörü (Forecasting)" Değil, Basit Bir "Kural" Olabilir?

Gerçek enerji trading şirketleri, çok karmaşık **tahmin modelleri** (makine öğrenmesi, zaman serisi tahmini vb.) kullanarak gelecekteki fiyatları öngörür ve buna göre strateji kurar. Ancak brief'in de belirttiği gibi, **projenizde bu seviyede bir karmaşıklık beklenmiyor** — "şu anki fiyat ve durum verildiğinde ne önerirsin" şeklinde basit, anlık bir kural yeterlidir. Bu, akademik bir "trading algoritması" değil, **"koşullu karar mekanizması" (conditional decision logic)** seviyesindedir — ki bu zaten backend geliştirmede çok sık karşılaşacağınız bir kalıptır (if-else zincirleri, iş kuralları motoru mantığı).

## SOC Sınırlarının Trading Kararına Etkisi

Trading kararı verirken SOC sınırlarını (Konu 1'de bahsedilen min/max güvenli sınırlar) göz ardı edemezsiniz:
- Depo zaten **max SOC'ye yakınsa**, fiyat ne kadar ucuz olursa olsun daha fazla depolayamazsınız (fiziksel sınır).
- Depo zaten **min SOC'ye yakınsa**, fiyat ne kadar yüksek olursa olsun satacak enerjiniz kalmamış olabilir.

Bu, trading mantığınızın Konu 1'deki "aşırı şarj/deşarj engelleme" kuralıyla doğal olarak entegre olması gerektiği anlamına gelir — iki kural birbirinden bağımsız değil, birbirini tamamlayan parçalardır.

## Basit Bir Örnek Akış (Endpoint Mantığı)

Brief'in önerdiği gibi bir endpoint şu şekilde çalışabilir:

**Girdi:** `{ "storageUnitId": "unit-01", "currentHour": 18 }`

**İşlem adımları:**
1. `currentHour` için mock fiyat listesinden fiyatı al (örn. saat 18 → 2900 TL/MWh)
2. İlgili `storageUnitId`'nin mevcut SOC'sini al (örn. %72)
3. Eşik kurallarını uygula: fiyat yüksek eşiğin üstünde mi? SOC min sınırın üstünde mi?
4. Karar üret: `{ "recommendation": "SAT", "reason": "Fiyat günün en yüksek dilimde ve SOC yeterli seviyede" }`

**Çıktı:** Kullanıcıya (frontend'de) net, açıklamalı bir öneri gösterilir — bu hem iş mantığını somutlaştırır hem de demo sunumunda etkileyici bir "canlı anlatım" imkânı sağlar (brief'in 5. bölümdeki kabul kriteri).

## Round-Trip Verimliliğin Trading Kararına Etkisi (İleri Seviye Bağlantı)

Gerçekçi bir arbitraj stratejisinde, sadece "fiyat farkı var mı" değil, "fiyat farkı **çevrim kaybını karşılıyor mu**" sorusu da önemlidir. Örneğin round-trip efficiency %90 ise (yani %10 kayıp varsa), enerjiyi depolayıp geri satmanın kârlı olması için satış fiyatının alış fiyatından **en az %10'dan fazla** yüksek olması gerekir — aksi halde kayıp, kâr marjını yer. Bu, isteğe bağlı ama etkileyici bir ek detay olabilir; temel kural olarak zorunlu değildir ama projenizi "gerçek iş mantığını anlamış" seviyesine taşır.

## Özet

| Kavram | Anlamı |
|---|---|
| Enerji arbitrajı | Ucuzken depola, pahalıyken sat mantığı |
| Kural tabanlı karar | Sabit eşik değerlerine dayalı basit if-else mantığı |
| Göreceli eşik | Günün fiyat dağılımına göre dinamik eşik (daha esnek) |
| SOC-fiyat entegrasyonu | Karar hem fiyata hem depo kapasitesine bakmalı |
| Verimlilik-kâr ilişkisi | Kârlı arbitraj için fiyat farkı, çevrim kaybını aşmalı (ileri seviye) |
