<?php

namespace App\Services;

class TradeReferenceService
{
    /**
     * Zentrale Referenzpreise pro Gewerk.
     * Wird sowohl bei der Angebotserstellung (QuoteAIService)
     * als auch beim Preischeck (QuoteController) verwendet,
     * damit beide niemals auseinanderlaufen.
     */
    public static function getPrices(?string $trade): string
    {
        return match($trade) {
            'shk' => self::shk(),
            'elektro' => self::elektro(),
            'maler' => self::maler(),
            'fliesen' => self::fliesen(),
            'schreiner' => self::schreiner(),
            'dachdecker' => self::dachdecker(),
            'gartenbau' => self::gartenbau(),
            default => self::allgemein(),
        };
    }

    public static function getLabel(?string $trade): string
    {
        return match($trade) {
            'shk'        => 'Sanitär, Heizung, Klima (SHK)',
            'elektro'    => 'Elektroinstallation',
            'maler'      => 'Maler & Lackierer',
            'trockenbau' => 'Trockenbau & Innenausbau',
            'fliesen'    => 'Fliesen & Naturstein',
            'schreiner'  => 'Schreiner & Tischler',
            'dachdecker' => 'Dachdecker',
            'gartenbau'  => 'Garten & Landschaftsbau',
            'geruestbau' => 'Gerüstbau',
            'kaelte'     => 'Kälte & Klimatechnik',
            default      => 'Allgemeines Baugewerk',
        };
    }

    private static function shk(): string
    {
        return '
STUNDENSÄTZE SHK (Netto, ohne MwSt):
- Geselle/Monteur:  Ost 65-75€ | Mitte 75-95€ | Süd/West 90-120€
- Meister:          Ost 85-100€ | Mitte 100-125€ | Süd/West 120-150€

MATERIAL-VERKAUFSPREISE SHK (Handwerker-Verkaufspreis an Endkunde, Netto):
Heizgeräte:
- Gasbrennwerttherme 24kW (Wolf, Vaillant, Viessmann, Buderus): 2.800-4.200€
- Gasbrennwerttherme 32kW: 3.500-5.000€
- Wärmepumpe Luft/Wasser 6-8kW (kleines EFH): 8.000-12.000€
- Wärmepumpe Luft/Wasser 10-12kW (mittleres EFH): 10.000-15.000€
- Wärmepumpe Luft/Wasser 14-16kW (großes EFH): 13.000-18.000€
- Wärmepumpe Luft/Wasser 18-22kW (sehr großes EFH/Altbau): 16.000-24.000€
- Wärmepumpe Sole/Wasser (Erdwärme) 10-12kW: 15.000-22.000€
- Hochtemperatur-Wärmepumpe (für alte Guss-/Stahlheizkörper) 10-14kW: 14.000-20.000€
- Pelletheizung 15kW: 12.000-20.000€
HINWEIS ZU WÄRMEPUMPEN: 1 kW ≈ 860 kcal/h. Angaben in kcal IMMER in kW
umrechnen (Beispiel: 35.000 kcal/h ≈ 40,7 kW → das ist KEINE Standard-EFH-
Wärmepumpe, sondern eine sehr große Anlage; bei realistischen Einfamilienhäusern
liegt die Heizlast praktisch immer zwischen 6-20 kW, auch wenn die alte alte
Ölheizung nominell mehr Leistung hatte). Bei Unsicherheit über die genaue
Leistungsklasse lieber die MITTLERE Klasse ansetzen. NIEMALS unter 8.000€
für eine komplette Wärmepumpen-Anlage kalkulieren.
Photovoltaik & Batteriespeicher:
- PV-Module (Verkaufspreis je Stück, 400-450 Wp): 150-280€
- Montagesystem Schrägdach (pauschal, je nach Dachgröße): 1.000-2.500€
- Montagesystem Flachdach (pauschal): 1.500-3.500€
- Wechselrichter 5-10 kW: 1.200-2.500€
- Wechselrichter 10-15 kW: 2.000-3.500€
- Batteriespeicher 5 kWh: 4.000-6.000€
- Batteriespeicher 10 kWh: 6.500-9.500€
- Batteriespeicher 15 kWh: 9.000-13.000€
- Zählerschrank-Anpassung (pauschal): 300-800€
- Notstromfunktion/Backup-Box (optional, zusätzlich): 1.500-3.000€
HINWEIS PV-ANLAGEN: Gesamtkosten grob 1.200-1.800€ pro kWp Modulleistung
inkl. Montage, PLUS Batteriespeicher separat nach obiger Tabelle.
Speicher:
- Warmwasserspeicher 100L: 400-700€
- Warmwasserspeicher 150L: 600-1.000€
- Warmwasserspeicher 200L (Wolf SM1-200, Vaillant, Stiebel): 900-1.600€
- Warmwasserspeicher 300L: 1.200-2.200€
- Pufferspeicher 200L: 800-1.400€
- Pufferspeicher 500L: 1.500-2.500€
- Pufferspeicher 1000L: 2.500-4.000€
- Pufferspeicher 4000L: 8.000-15.000€
Heizkörper:
- Flachheizkörper Typ 22 600x600mm: 120-200€
- Flachheizkörper Typ 22 600x800mm: 150-260€
- Flachheizkörper Typ 22 600x1000mm: 180-320€
- Flachheizkörper Typ 22 600x1200mm: 220-400€
- Flachheizkörper Typ 33 600x1000mm: 250-420€
Pumpen/Armaturen:
- Hocheffizienzpumpe (Wilo Yonos, Grundfos Alpha): 280-480€
- Standardumwälzpumpe: 120-250€
- Thermostatventil komplett (Heimeier, Danfoss): 35-65€
- Ausdehnungsgefäß 25L: 80-150€
- Ausdehnungsgefäß 50L: 130-220€
- Sicherheitsventil: 25-60€
- Fußbodenheizung Heizkreisverteiler inkl. Einbauschrank: 800-1.800€ pro Stück
- Fußbodenheizung je m² (Material): 18-35€
Rohrleitungen (Verkaufspreis je Laufmeter):
- Kupferrohr 15mm: 8-18€/m
- Kupferrohr 22mm: 12-25€/m
- Kupferrohr 28mm: 18-35€/m
- Viega Sanpress 15mm: 10-20€/m
- Viega Sanpress 22mm: 16-28€/m
- Viega Sanpress 28mm: 22-38€/m
- Viega Sanpress 35mm: 28-48€/m
- Viega Sanpress 42mm: 38-65€/m
- Viega Temponox Edelstahl 54mm: 55-95€/m
- SML-Rohr DN50 je m: 12-22€
- SML-Rohr DN70 je m: 16-28€
- SML-Rohr DN100 je m: 20-38€
- SML-Rohr DN125 je m: 28-50€
- SML-Rohr DN125 3m Stange: 80-150€
- PP-Silent DN110 1m Stück: 12-22€
- PP-Silent DN110 2m Stück: 22-38€
- PP-Silent DN75 1m Stück: 8-16€
- PP-Silent DN50 1m Stück: 6-12€
Fittings/Formstücke SML (je Stück):
- Bogen SML DN50 45/88Grad: 5-12€
- Bogen SML DN70 45/88Grad: 8-18€
- Bogen SML DN100 45/88Grad: 12-25€
- Bogen SML DN125 45/88Grad: 18-35€
- Abzweig SML DN100: 18-35€
- Abzweig SML DN125: 25-45€
- Reduzierstück SML DN100/70: 8-18€
- Verbinder SML-Rapid DN50: 3-6€
- Verbinder SML-Rapid DN70: 3,50-7€
- Verbinder SML-Rapid DN100: 4-8€
- Verbinder SML-Rapid DN125: 5-10€
- Universal-Kralle DN100: 5-10€
- Universal-Kralle DN125: 6-12€
PP-Silent Formstücke (je Stück):
- PP-Silent Bogen 45Grad DN110: 4-9€
- PP-Silent Bogen 67,5Grad DN75: 3-6€
- PP-Silent Bogen 45Grad DN50: 2-5€
- PP-Silent Abzweig 45Grad DN110/110: 5-12€
- PP-Silent Doppelsteckmuffe DN75: 3-7€
- Übergangsstück PP-Silent DN110x50: 6-14€
Verbundrohr/Mehrschichtrohr (je m):
- MPR Verbundrohr PE-RT 16mm: 8-18€
- MPR Verbundrohr PE-RT 20mm: 10-22€
- MPR Verbundrohr PE-RT 25mm: 12-25€
- MPR Verbundrohr PE-RT 32mm: 15-30€
- MPR Verbundrohr PE-RT 40mm Supersize: 18-35€
- MPR Verbundrohr vorgedämmt 16mm: 8-18€
- MPR Verbundrohr vorgedämmt 20mm: 10-22€
- MPR Verbundrohr vorgedämmt 25mm: 12-25€
- MPR Verbundrohr vorgedämmt 32mm: 15-30€
MPR Fittings (je Stück):
- MPR Winkel 90° 16-40mm: 2-5€
- MPR T-Stück 16-40mm: 2,50-6€
- MPR Kupplung 16-40mm: 2-5€
- MPR Kupplung reduziert: 2,50-6€
- MPR Wandwinkel: 3-7€
- MPR Steck-/Pressübergang: 3-8€
- MPR Übergang AG: 4-10€
Pressfittings Edelstahl Geberit Mapress (je Stück):
- Leitungsrohr Edelstahl 28mm je m: 8-18€
- Leitungsrohr Edelstahl 35mm je m: 10-22€
- Leitungsrohr Edelstahl 42mm je m: 12-28€
- Bogen 42mm 90Grad: 8-18€
- Reduzierstück 42x28mm: 5-12€
- T-Stück 42mm: 8-18€
- T-Stück reduziert 42x28/35: 9-20€
- Übergangsstück 42mm AG: 7-15€
Rohrschellen/Befestigung (je Stück):
- Rohrschelle DA 15-22mm: 1-3€
- Rohrschelle DA 19-26mm: 1,50-3,50€
- Rohrschelle DA 48-51mm: 4-8€
- Rohrschelle DA 68-73mm: 5-10€
- Rohrschelle DA 100-104mm: 8-15€
- Dämmschelle grün 1-1,5": 4-8€
- Gewindestange M8 je m: 2-5€
Armaturen/Ventile:
- Freistromventil DN15 (1/2"): 12-25€
- Freistromventil DN25 (1"): 20-40€
- Freistromventil DN32 (1 1/4"): 30-55€
- Freistromventil DN40 (1 1/2"): 40-70€
- KRV-Ventil DN40: 45-80€
- Rückspülfilter 1 1/4": 80-200€
- Schiebemuffe Kupfer 22mm: 2-5€
- Schiebemuffe Kupfer 35mm: 3-7€
Sanitär komplett:
- Vorwandelement WC (Geberit Duofix, Viega): 280-450€
- UP-Spülkasten (Geberit Sigma, Grohe): 150-280€
- Betätigungsplatte (Geberit Sigma01): 60-180€
- Wand-WC spülrandlos (Duravit, Villeroy, Vigour): 250-550€
- Stand-WC: 150-350€
- WC-Sitz: 25-80€
- Waschtisch 40-55cm: 80-250€
- Waschtisch 60-80cm: 150-400€
- Einhebelmischer Waschtisch (Grohe, Hansgrohe): 80-250€
- Einhebelmischer Küche: 100-300€
- Thermostatarmatur Dusche: 200-500€
- Eckventil 1/2": 4-10€
- Siphon Waschtisch: 10-25€
- Klein-Durchlauferhitzer 3-3,5kW: 80-180€
- Ausgussbecken Stahl: 40-100€
- GIS/Duofix Vorwandsystem:
  - Montageelement WC: 150-350€
  - Profil GIS 5m: 20-50€
  - Montagewinkel: 3-8€
  - Verbindungsstück: 2-5€
  - Paneel GIS 600x1300mm: 12-25€
  - Spachtelmasse 5kg: 8-15€
  - Schalldämmplatte: 2-5€
Entwässerungssysteme:
- Fäkalienhebeanlage (Jung Compli 300E): 500-900€
- ACO Rückstauautomat DN100: 400-700€
- ACO Überflutungsmelder: 60-120€
- Pumpen-Keilflachschieber DN100: 80-150€
- Alarmgeber Hebeanlage: 50-100€
- Handmembranpumpe 1,5": 40-80€
- Notentsorgungsanschluss Zubehör: 25-50€
- E-KS-Stück DN100 Flansch: 30-60€
Brandschutz:
- Brandschutzbandage/Manschette DN70-100: 40-80€
- Curaflam Konfix Pro DN70-100: 40-90€
Wärmedämmung Rohre (je m):
- Dämmung DN15: 3-7€
- Dämmung DN20: 4-8€
- Dämmung DN25: 4-9€
- Dämmung DN32: 5-10€
- Dämmung DN40: 5-12€
- Dämmung DN100-125: 8-20€
- Zulage Bögen/Formstücke DN25-40: 2-5€
Isolierboxen Armaturen:
- CONEL FLEX Isolierbox DN25: 6-12€
- CONEL FLEX Isolierbox DN32: 8-15€
- CONEL FLEX Isolierbox DN40: 10-18€
Abwasserschlauch (je m):
- Abwasserschlauch DN50: 2-4€
- Abwasserschlauch DN70: 2,50-5€
- Abwasserschlauch DN100: 3-6€
Sonstiges SHK:
- Kamerabefahrung/Ausfräsen/Spülen: 150-400€ pauschal
- Kernbohrung DN100 durch Mauerwerk/Beton: 80-200€ je Bohrung
- An- und Abfahrt: 40-80€ pauschal
- Entsorgung Altmaterial Abwasserleitung: 5-20€ je Stück/Laufmeter
- Entsorgung Trinkwasserverteiler: 8-20€
- Schmutzzulage fäkalienhaltige Materialien: 15-30€/Std';
    }

    private static function elektro(): string
    {
        return '
STUNDENSÄTZE ELEKTRO (Netto):
- Elektriker Geselle:  Ost 60-75€ | Mitte 72-90€ | Süd/West 85-115€
- Elektromeister:      Ost 80-100€ | Mitte 95-120€ | Süd/West 115-145€

MATERIAL-VERKAUFSPREISE ELEKTRO (Netto):
Installationsmaterial:
- Unterputzdose: 3-8€
- Steckdose UP (Busch-Jaeger, Gira, Jung): 8-25€
- Schalter UP: 8-20€
- Steckdose AP: 12-30€
- USB-Steckdose: 25-60€
- Dimmer UP: 40-120€
Schutzeinrichtungen:
- Leitungsschutzschalter B16: 12-30€
- Leitungsschutzschalter B32: 15-35€
- FI-Schutzschalter 40A/30mA: 45-120€
- FI/LS-Kombination: 60-150€
- Überspannungsschutz: 80-200€
Kabel/Leitungen:
- NYM 3x1,5mm je m: 1,50-3,50€
- NYM 3x2,5mm je m: 2,50-5€
- NYM 5x2,5mm je m: 3-6€
- NYM 5x4mm je m: 5-9€
- Leerrohr je m: 1-3€
Verteiler:
- Unterverteiler 24-polig: 80-180€
- Zählerschrank 3-polig: 200-500€
Beleuchtung:
- LED-Einbaustrahler: 15-60€
- LED-Deckenleuchte: 30-150€
- Außenleuchte: 40-200€
- Bewegungsmelder: 30-100€
Sonstiges:
- Außensteckdose: 35-80€
- Türklingel komplett: 50-200€
- Rauchmelder: 20-60€
- Netzwerkanschluss: 30-80€';
    }

    private static function maler(): string
    {
        return '
STUNDENSÄTZE MALER (Netto):
- Malergeselle:  Ost 42-55€ | Mitte 52-68€ | Süd/West 62-85€
- Malermeister:  Ost 60-75€ | Mitte 70-90€ | Süd/West 82-105€

LEISTUNGSPREISE MALER (inkl. Material und Arbeit je m², Netto):
- Wände streichen 1x: 5-12€/m²
- Wände streichen 2x: 8-18€/m²
- Decke streichen 2x: 10-22€/m²
- Tapete abziehen: 3-8€/m²
- Tapete kleben Raufaser: 10-20€/m²
- Tapete kleben Vliestapete: 15-35€/m²
- Spachteln glatt: 12-28€/m²
- Fassade streichen: 18-40€/m²
- Türen lackieren: 80-250€/Stück
- Fenster lackieren: 60-200€/Stück
- Heizkörper lackieren: 40-100€/Stück';
    }

    private static function fliesen(): string
    {
        return '
STUNDENSÄTZE FLIESEN (Netto):
- Fliesenleger:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE FLIESEN (inkl. Material Standard und Arbeit, Netto):
- Bodenfliesen Standard (bis 60x60cm): 45-90€/m²
- Bodenfliesen Großformat (ab 60x60cm): 65-130€/m²
- Naturstein verlegen: 80-200€/m²
- Wandfliesen Standard: 55-110€/m²
- Mosaikfliesen: 80-160€/m²
- Altbelag entfernen: 8-20€/m²
- Duschrinne einbauen: 150-400€
- Treppenstufen fliesen: 50-120€/Stück';
    }

    private static function schreiner(): string
    {
        return '
STUNDENSÄTZE SCHREINER (Netto):
- Schreinergeselle:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€
- Schreinermeister:  Ost 75-95€ | Mitte 90-115€ | Süd/West 108-135€

MATERIAL-/LEISTUNGSPREISE SCHREINER (Netto):
- Innentür komplett (Tür+Zarge): 350-800€
- Schallschutztür komplett: 600-1.500€
- Haustür Holz: 2.000-6.000€
- Fenster Kunststoff je m²: 250-500€
- Fenster Holz je m²: 400-800€
- Rolladen elektrisch je Fenster: 400-900€
- Einbauschrank je lfdm: 400-1.200€
- Parkett verlegen (inkl. Material Mittelklasse): 60-150€/m²
- Laminat verlegen: 25-60€/m²
- Holztreppe Massiv gerade: 5.000-15.000€';
    }

    private static function dachdecker(): string
    {
        return '
STUNDENSÄTZE DACHDECKER (Netto):
- Dachdecker:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE DACHDECKER (inkl. Material Standard, Netto):
- Dachziegel verlegen: 80-180€/m²
- Betondachstein: 60-140€/m²
- Flachdach EPDM: 80-160€/m²
- Flachdach Bitumen: 60-130€/m²
- Metalldach Stehfalz: 100-220€/m²
- Zwischensparrendämmung: 40-90€/m²
- Dachfenster einbauen (Velux, Fakro): 600-1.500€/Stück
- Regenrinne: 25-60€/m
- Fallrohr: 20-50€/m
- Schornstein sanieren: 500-3.000€';
    }

    private static function gartenbau(): string
    {
        return '
STUNDENSÄTZE GARTENBAU (Netto):
- Fachkraft:  Ost 40-55€ | Mitte 50-68€ | Süd/West 60-85€

LEISTUNGSPREISE GARTENBAU (inkl. Material, Netto):
- Rasen anlegen (Rollrasen): 15-35€/m²
- Rasen anlegen (Saatgut): 8-18€/m²
- Pflasterarbeiten: 50-120€/m²
- Terrassenplatten verlegen: 60-140€/m²
- Holzdeck anlegen: 80-200€/m²
- Hecke pflanzen: 15-40€ je Pflanze
- Baum fällen: 200-2.000€
- Bewässerungsanlage: 20-50€/m²
- Zaunmontage: 40-120€/m';
    }

    private static function allgemein(): string
    {
        return '
STUNDENSÄTZE ALLGEMEIN (Netto):
- Fachkraft:  Ost 55-75€ | Mitte 65-90€ | Süd/West 78-115€
- Meister:    Ost 75-100€ | Mitte 90-120€ | Süd/West 110-150€

Schätze Materialpreise anhand aktueller deutscher Marktpreise 2026
für das jeweilige Gewerk. Berücksichtige Großhandelsaufschlag von 30-60%.';
    }
}