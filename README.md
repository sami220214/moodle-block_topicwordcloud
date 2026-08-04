# block_topicwordcloud

Sanapilvi-lohko Moodleen. Lohko kerää opiskelijoiden syöttämiä sanoja, yhdistää samat sanat case-insensitive-logiikalla, poistaa stop-sanoja ja näyttää tulokset sanapilvenä sekä analytiikkana.

## Vaatimukset

- Moodle 4.5.10
- PHP-ympäristö, joka vastaa Moodle 4.5.10 -asennuksen vaatimuksia
- Oikeudet asentaa uusia pluginin tiedostoja Moodle-palvelimelle

## Asennus

1. Kopioi tämän projektin kansio Moodle-palvelimelle polkuun `blocks/topicwordcloud`.
2. Varmista, että plugin-kansion nimi on täsmälleen `topicwordcloud`.
3. Lopputuloksen tulee näyttää tältä: `moodle/blocks/topicwordcloud/version.php`.
4. Avaa Moodle selaimessa ylläpitäjänä.
5. Mene kohtaan `Site administration > Notifications` tai suoraan osoitteeseen `/admin/index.php`.
6. Moodle tunnistaa uuden lohkon ja käynnistää asennuksen.
7. Hyväksy asennus, jolloin Moodle luo pluginin tietokantataulut:
   - `block_topicwordcloud_entry`
   - `block_topicwordcloud_word`
8. Tyhjennä tarvittaessa välimuistit kohdasta `Site administration > Development > Purge caches`.

## Käyttöönotto kurssilla

1. Avaa kurssi.
2. Laita muokkaustila päälle.
3. Lisää lohko: `Sanapilvi`.
4. Avaa lohkon asetukset.
5. Määritä halutut asetukset:
   - kysymys tai tehtävänanto
   - sallitaanko useita vastauksia per opiskelija
   - sanojen maksimimäärä käyttäjää kohden
   - sanojen järjestys: aikajärjestys, aakkosjärjestys tai esiintyvyys laskevasti
   - avautumis- ja sulkeutumisaika
   - vaaditaanko moderointi ennen näkyvyyttä
   - näytetäänkö opiskelijoiden nimet opettajalle
   - lisästop-sanat

## Mitä lohko tekee

- Opiskelija voi syöttää yhden tai useamman sanan tekstikenttään.
- Sanat tallennetaan tietokantaan käyttäjä-, kurssi- ja kontekstitiedoilla.
- Samat sanat yhdistetään kirjainkoosta riippumatta.
- Yleisiä stop-sanoja poistetaan.
- Sanapilvi päivittyy AJAX-kutsulla automaattisesti.
- Sanat voidaan näyttää aikajärjestyksessä, aakkosjärjestyksessä tai esiintyvyyden mukaan laskevasti.
- Opettaja voi nollata sanapilven.
- Opettaja voi poistaa yksittäisiä sanoja.
- Opettaja voi hyväksyä odottavia sanoja, jos moderointi on käytössä.
- Lohko näyttää analytiikan:
  - vastausten määrä
  - uniikkien vastaajien määrä
  - uniikkien sanojen määrä
  - sanafrekvenssit

## Lokitus

Lohko kirjoittaa käyttäjän varsinaiset toiminnot Moodlen lokitietoihin Events API:n kautta:

- `\block_topicwordcloud\event\cloud_viewed` kun lohko renderöidään kurssisivulle
- `\block_topicwordcloud\event\submission_submitted` kun käyttäjä lähettää sanoja
- `\block_topicwordcloud\event\word_approved` kun opettaja hyväksyy odottavan sanan
- `\block_topicwordcloud\event\word_deleted` kun opettaja poistaa sanan
- `\block_topicwordcloud\event\cloud_reset` kun opettaja nollaa sanapilven

Automaattista AJAX-refresh-pollausta ei lokiteta, jotta lokit eivät täyty taustapäivityksistä.

## Testaus asennuksen jälkeen

Suositeltu minimitestaus:

1. Lisää lohko kurssille opettajana.
2. Aseta lohkoon kysymys.
3. Kirjaudu opiskelijana ja lähetä muutama sana.
4. Varmista, että sanat näkyvät sanapilvessä.
5. Testaa, että sama sana eri kirjainkoossa yhdistyy samaksi sanaksi.
6. Testaa stop-sanan suodatus.
7. Testaa maksimisanamäärä per käyttäjä.
8. Testaa sanojen järjestysasetukset.
9. Testaa aikarajaus.
10. Testaa moderointi:
    - sana ei näy ennen hyväksyntää
    - opettaja voi hyväksyä sanan
11. Testaa opettajan reset-toiminto.

## Kehittäjälle

Tärkeimmät tiedostot:

- `block_topicwordcloud.php` lohkon renderöinti
- `edit_form.php` lohkon asetukset
- `classes/external/*` ja `db/services.php` External Services AJAX-toiminnot
- `classes/local/manager.php` sanapilven liiketoimintalogiikka
- `db/install.xml` tietokantataulut
- `db/access.php` capabilityt
- `amd/src/cloud.js` selainpuolen logiikka
- `templates/*.mustache` selaimessa renderöitävät Moodle-templatet
- `styles.css` ulkoasu

## Tietosuoja

Plugin tallentaa opiskelijan lähettämän raakatekstin, käsitellyt sanat, käyttäjätunnisteen, kurssitunnisteen, kontekstitunnisteen, lohkoinstanssin tunnisteen sekä tallennusajat. Jos asetus on käytössä, opettaja voi nähdä sanakohtaiset lähettäjien nimet analytiikassa.

Tietojen vienti ja poistaminen on toteutettu Moodlen Privacy API:n kautta. Plugin ei lähetä tietoja ulkoisiin palveluihin eikä tallenna API-avaimia lähdekoodiin tai tietokantaan.
## Huomioita

- Tämä plugin on tehty Moodle 4.5.10 -ympäristöä varten.
- Jos käytät tuotantoympäristössä, testaa ensin kehitys- tai testiympäristössä.
- Jos AMD-JavaScriptiin tehdään muutoksia, ne kannattaa kääntää normaalin Moodle-työnkulun mukaan.