<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Finnish strings for block_topicwordcloud.
 *
 * @package   block_topicwordcloud
 * @copyright 2026 Sami Simpanen
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Sanapilvi';
$string['eventcloudviewed'] = 'Sanapilvi katsottu';
$string['eventsubmissionsubmitted'] = 'Sanat lähetetty';
$string['eventcloudreset'] = 'Sanapilvi nollattu';
$string['eventworddeleted'] = 'Sana poistettu';
$string['eventwordapproved'] = 'Sana hyväksytty';
$string['topicwordcloud:addinstance'] = 'Lisää uusi Sanapilvi-lohko';
$string['topicwordcloud:myaddinstance'] = 'Lisää uusi Sanapilvi-lohko Oma etusivu -sivulle';
$string['topicwordcloud:submitwords'] = 'Lähetä sanoja sanapilveen';
$string['topicwordcloud:viewanalytics'] = 'Näytä sanapilven analytiikka';
$string['topicwordcloud:managewords'] = 'Hallitse sanapilven sanoja';
$string['defaultprompt'] = 'Mitä sanoja liität tähän aiheeseen?';
$string['prompttext'] = 'Kysymys tai tehtävänanto';
$string['allowmultiple'] = 'Salli useita vastauksia per opiskelija';
$string['maxwordsperuser'] = 'Sanojen maksimimäärä käyttäjää kohden';
$string['wordorder'] = 'Sanojen järjestys';
$string['wordorder_chronological'] = 'Aikajärjestys, ensimmäisen lisäyksen mukaan';
$string['wordorder_alphabetical'] = 'Aakkosjärjestys';
$string['wordorder_frequency'] = 'Esiintyvyys, suurin ensin';
$string['opentime'] = 'Aukeaa';
$string['closetime'] = 'Sulkeutuu';
$string['moderationrequired'] = 'Vaadi hyväksyntä ennen näkyvyyttä';
$string['showusernames'] = 'Näytä osallistujien nimet opettajille';
$string['customstopwords'] = 'Lisästop-sanat';
$string['inputlabel'] = 'Sanat';
$string['inputplaceholder'] = 'Kirjoita yksi tai useampi sana, erottele välilyönnillä, pilkulla tai rivinvaihdolla';
$string['submitwords'] = 'Lähetä';
$string['loading'] = 'Ladataan sanapilveä...';
$string['opennow'] = 'Vastaaminen on avoinna.';
$string['openuntil'] = 'Vastaaminen on avoinna asti {$a}.';
$string['opensat'] = 'Vastaaminen avautuu {$a}.';
$string['closedat'] = 'Vastaaminen sulkeutui {$a}.';
$string['submissionsaved'] = 'Sanasi tallennettiin.';
$string['submissionstoredpending'] = 'Sanasi tallennettiin ja odottavat hyväksyntää.';
$string['submissionclosed'] = 'Vastausaika on suljettu.';
$string['multipleanswersdisabled'] = 'Tässä lohkossa sallitaan vain yksi vastaus per opiskelija.';
$string['maxwordsreached'] = 'Voit lähettää tähän lohkoon enintään {$a} sanaa.';
$string['nowordsdetected'] = 'Kelvollisia sanoja ei löytynyt. Tarkista stop-sanat ja välimerkit.';
$string['emptycloud'] = 'Sanapilvi on vielä tyhjä.';
$string['emptyanalytics'] = 'Analytiikkaa ei ole vielä näytettäväksi.';
$string['emptypending'] = 'Hyväksyttäviä sanoja ei ole.';
$string['analyticsheading'] = 'Analytiikka';
$string['manageheading'] = 'Opettajan hallinta';
$string['pendingheading'] = 'Odottaa hyväksyntää';
$string['responses'] = 'Vastaukset';
$string['responders'] = 'Uniikit vastaajat';
$string['uniquewords'] = 'Uniikit sanat';
$string['pendingcount'] = 'Odottaa hyväksyntää';
$string['remainingwords'] = 'Sanoja jäljellä';
$string['wordcolumn'] = 'Sana';
$string['countcolumn'] = 'Määrä';
$string['userscolumn'] = 'Käyttäjät';
$string['actioncolumn'] = 'Toiminnot';
$string['deleteword'] = 'Poista';
$string['approveword'] = 'Hyväksy';
$string['resetcloud'] = 'Nollaa sanapilvi';
$string['confirmreset'] = 'Nollataanko sanapilvi ja poistetaanko kaikki tallennetut vastaukset?';
$string['confirmdeleteword'] = 'Poistetaanko kaikki tämän sanan esiintymät sanapilvestä?';
$string['confirmapproveword'] = 'Hyväksytäänkö kaikki tämän sanan odottavat esiintymät?';
$string['cloudresetdone'] = 'Sanapilvi nollattiin.';
$string['worddeleted'] = 'Sana "{$a}" poistettiin.';
$string['wordapproved'] = 'Sana "{$a}" hyväksyttiin.';
$string['invalidword'] = 'Virheellinen sana.';
$string['invalidaction'] = 'Virheellinen toiminto.';
$string['invalidblockinstance'] = 'Sanapilvilohkon instanssia ei voitu ladata.';
$string['err_maxwords'] = 'Sanojen maksimimäärän tulee olla vähintään 1.';
$string['err_wordorder'] = 'Valitse kelvollinen sanojen järjestys.';
$string['err_closetime'] = 'Sulkeutumisajan on oltava avaamisajan jälkeen.';
$string['privacy:metadata:entries'] = 'Tallentaa sanapilven raakavastaukset.';
$string['privacy:metadata:words'] = 'Tallentaa sanapilveä varten käsitellyt sanat.';
$string['privacy:metadata:blockinstanceid'] = 'Lohkoinstanssin tunniste.';
$string['privacy:metadata:courseid'] = 'Kurssin tunniste.';
$string['privacy:metadata:contextid'] = 'Lohkon kontekstin tunniste.';
$string['privacy:metadata:userid'] = 'Vastaajan käyttäjätunniste.';
$string['privacy:metadata:rawtext'] = 'Vastaajan lähettämä raakateksti.';
$string['privacy:metadata:timecreated'] = 'Tallennusajankohta.';
$string['privacy:metadata:timemodified'] = 'Viimeisin muokkausajankohta.';
$string['privacy:metadata:entryid'] = 'Viittaus alkuperäiseen vastaukseen.';
$string['privacy:metadata:displayword'] = 'Sanan näkyvä alkuperäinen muoto.';
$string['privacy:metadata:normalizedword'] = 'Sanan normalisoitu muoto frekvenssilaskentaa varten.';
$string['privacy:metadata:approved'] = 'Tieto siitä, onko sana hyväksytty näkyväksi.';