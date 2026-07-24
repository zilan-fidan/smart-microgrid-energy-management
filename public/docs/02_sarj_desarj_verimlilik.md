# 2. Şarj-Deşarj Döngüsü ve Verimlilik Kaybı

## Şarj-Deşarj Döngüsü Nedir?

Bir enerji depolama sisteminde temel işlem döngüsü iki yönlüdür:

- **Şarj (Charge):** Sisteme dışarıdan enerji verilir, batarya doldurulur. SOC artar.
- **Deşarj (Discharge):** Sistemden enerji çekilir, batarya boşaltılır. SOC azalır.

Bir **tam döngü (full cycle)**, bataryanın bir kez tamamen doldurulup tamamen boşaltılması anlamına gelir (örneğin %100'den %0'a, sonra tekrar %100'e). Gerçekte çoğu kullanım **kısmi döngü (partial cycle)** şeklindedir — batarya hiçbir zaman tam olarak %0 veya %100'e gitmez, %30-%70 arası gibi bir bantta gezinir.

## Neden Verimlilik Kaybı Oluşur?

İşte projenin en önemli teknik noktalarından biri: **Depoya verdiğiniz enerjinin tamamını geri alamazsınız.**

Bunun sebepleri:

1. **Isı kaybı (Joule ısınması):** Bataryanın iç direnci nedeniyle, akım aktığında enerjinin bir kısmı ısıya dönüşür ve kaybolur (tıpkı bir telin ısınması gibi).
2. **Dönüşüm kayıpları:** Şebekeden gelen enerji genelde AC (alternatif akım), bataryalar ise DC (doğru akım) ile çalışır. Bu dönüşüm işlemini yapan **inverter/converter** cihazları da %100 verimli değildir, her dönüşümde küçük bir kayıp olur (hem şarjda hem deşarjda, yani iki kez).
3. **Kimyasal reaksiyon kayıpları:** Bataryanın içindeki kimyasal şarj/deşarj süreci de ideal değildir; bir miktar enerji kimyasal yan reaksiyonlarla kaybolur.
4. **Kendi kendine deşarj (self-discharge):** Batarya hiç kullanılmasa bile zamanla küçük miktarda enerji kaybeder (bu, "round-trip" hesaplamasının dışında ayrı bir kavramdır, ama bilinmesi faydalıdır).

## Round-Trip Efficiency (Gidiş-Dönüş Verimliliği) — Temel Formül

Bu, projenizde uygulamanız beklenen en önemli hesaplamalardan biridir:

```
Round-Trip Efficiency (%) = (Deşarjda geri alınan enerji / Şarjda depoya verilen enerji) × 100
```

**Örnek:**
- Depoya 100 kWh enerji verdiniz (şarj).
- Daha sonra bu depodan 90 kWh enerji geri aldınız (deşarj).
- Round-trip efficiency = (90 / 100) × 100 = **%90**

Gerçek dünyada lityum-iyon bataryalı sistemlerde bu değer genellikle **%85 – %95** aralığındadır (brief'te de bu aralık belirtiliyor). Kurşun-asit bataryalarda bu oran daha düşüktür (~%70-80), pompajlı hidroelektrik depolamada ise ~%70-85 civarındadır.

## Verim ile Kayıp Arasındaki İlişki

Brief'te özellikle vurgulanan bir nokta: **"Verim = kayıp değildir; kayıp = 1 − verim."**

```
Kayıp (%) = 100% − Round-Trip Efficiency (%)
```

Yukarıdaki örnekte verim %90 ise, kayıp %10'dur. Yani 100 kWh verdiniz, 90 kWh geri aldınız, aradaki 10 kWh **çevrim kaybı** olarak sistemden "buharlaştı" (aslında ısıya dönüştü).

## Bunu Yazılımda Nasıl Modellersiniz?

Basit bir yaklaşım şudur:

1. Bir sabit verimlilik oranı tanımlarsınız (örn. `%90` veya `0.90`).
2. Şarj işleminde: kullanıcı "50 kWh şarj et" dediğinde, bataryaya aslında `50 × verim` kadar enerji eklenebilir (yani şarj tarafında da bir kayıp uygulanabilir) **ya da** kayıp sadece deşarjda uygulanabilir — bu, sizin tasarım tercihinizdir, literatürde her iki yaklaşım da kullanılır. Basit ve tutarlı bir yöntem seçip belgelemeniz yeterli.
3. Deşarj işleminde: bataryadan "50 kWh çek" dendiğinde, kullanıcıya iletilen/şebekeye verilen enerji `50 × verim` kadar olur; SOC'den düşülen miktar ise talep edilen tam miktardır (ya da tam tersi — yine tasarım tercihi).

**Önerilen basit model (uygulaması kolay):**
```
Şarjda: SOC'ye eklenen enerji = Girdi enerji × √verim
Deşarjda: Kullanıcıya/şebekeye verilen enerji = SOC'den çekilen enerji × √verim
```
Bu yaklaşımda toplam round-trip kaybı iki işleme (şarj + deşarj) eşit olarak bölünür (√verim × √verim = verim). Ancak projenin karmaşıklığını basit tutmak istiyorsanız, kaybı tek seferde (örneğin sadece deşarjda) uygulamak da kabul edilebilir — önemli olan tutarlı ve açıklanabilir bir mantık kurmanızdır.

## Neden Bu Kural "Anlamlı bir İş Mantığı Kuralı" Sayılır?

Brief'in 5. bölümünde belirtilen "sadece veri saklayıp gösteren bir uygulama yeterli değil" şartını karşılamak için, round-trip verimlilik hesaplaması iyi bir adaydır çünkü:
- Gerçek bir fiziksel/mühendislik kavramını temsil eder
- Basit bir "ekle/çıkar" işleminden daha fazlasını gerektirir (oransal hesaplama)
- Sistem çıktısında somut, gösterilebilir bir sonuç üretir (örn. "Bu işlemde %8 kayıp oldu")

## Döngü Sayısı ile İlişkisi (İleri Seviye Not)

Gerçek sistemlerde, bataryanın toplam ömrü genellikle **döngü sayısı** (cycle count) ile ölçülür (örn. "3000 tam döngü ömrü"). Her döngü hem SOH'u biraz düşürür hem de zamanla verimlilik oranı da hafifçe azalabilir. Bu, projenizin zorunlu kapsamı dışındadır ama isterseniz "her X işlemden sonra verimlilik %0.1 azalsın" gibi basit bir kural ekleyerek sisteminizi daha gerçekçi hale getirebilirsiniz.

## Özet

| Kavram | Anlamı |
|---|---|
| Şarj | Enerjiyi depoya verme, SOC artışı |
| Deşarj | Enerjiyi depodan çekme, SOC azalışı |
| Round-trip efficiency | Geri alınan enerji / verilen enerji |
| Kayıp | 1 − verim |
| Tipik değer | %85–%95 (lityum-iyon için) |
