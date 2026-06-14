<?php
/**
 * Nepal Geographic Data — Province → District → Local Government
 * Source: LGCDP / Government of Nepal official list
 * 7 Provinces · 77 Districts · 753 Local Governments
 */
function nepalProvinces(): array {
    return array_keys(nepalGeo());
}

// नेपालीमा: nepalDistricts() — yo function le aafno kaam garchha
function nepalDistricts(?string $province = null): array {
    $geo = nepalGeo();
    if ($province && isset($geo[$province])) {
        return array_keys($geo[$province]);
    }
    $out = [];
    foreach ($geo as $dists) foreach ($dists as $d => $_) $out[] = $d;
    return $out;
}

// नेपालीमा: nepalLocalGovts() — yo function le aafno kaam garchha
function nepalLocalGovts(string $district): array {
    foreach (nepalGeo() as $dists) {
        if (isset($dists[$district])) return $dists[$district];
    }
    return [];
}

// नेपालीमा: nepalGeo() — yo function le aafno kaam garchha
function nepalGeo(): array {
    static $data = null;
    if ($data !== null) return $data;
    $data = [

/* ═══════════════════════════════════════════════
   1. KOSHI PROVINCE
═══════════════════════════════════════════════ */
'Koshi' => [
  'Taplejung'     => ['Phungling Municipality','Sirijangha Rural Municipality','Mikwakhola Rural Municipality','Meringden Rural Municipality','Phaktanglung Rural Municipality','Sidingba Rural Municipality','Maiwakhola Rural Municipality','Phalelung Rural Municipality','Pathivara Yangwarak Rural Municipality'],
  'Panchthar'     => ['Phidim Municipality','Hilihang Rural Municipality','Kummayak Rural Municipality','Tumbewa Rural Municipality','Phungling Rural Municipality','Yangwarak Rural Municipality','Miklajung Rural Municipality','Falelung Rural Municipality'],
  'Ilam'          => ['Ilam Municipality','Deumai Municipality','Mai Municipality','Suryodaya Municipality','Chulachuli Rural Municipality','Fakfokthum Rural Municipality','Maijogmai Rural Municipality','Mangsebung Rural Municipality','Rong Rural Municipality','Sandakpur Rural Municipality'],
  'Dhankuta'      => ['Dhankuta Municipality','Pakhribas Municipality','Sahidbhumi Rural Municipality','Chhathar Jorpati Rural Municipality','Sangurigadhi Rural Municipality','Mahalaxmi Rural Municipality','Khalsa Devi Rural Municipality'],
  'Terhathum'     => ['Myanglung Municipality','Aathrai Tribeni Rural Municipality','Chhathar Rural Municipality','Fedap Rural Municipality','Laligurans Rural Municipality','Phedikhola Rural Municipality','Phedap Rural Municipality'],
  'Sankhuwasabha' => ['Khandbari Municipality','Chainpur Municipality','Dharmadevi Municipality','Madi Municipality','Panchkhapan Municipality','Bhotkhola Rural Municipality','Chichila Rural Municipality','Makalu Rural Municipality','Hatuwagadhi Rural Municipality','Sabhapokhari Rural Municipality'],
  'Bhojpur'       => ['Bhojpur Municipality','Shadananda Municipality','Aamchowk Rural Municipality','Arun Rural Municipality','Hatuwagadhi Rural Municipality','Pauwadungma Rural Municipality','Ramprasad Rai Rural Municipality','Salpasilichho Rural Municipality','Tyamkemaiyung Rural Municipality'],
  'Solukhumbu'    => ['Solududhkunda Municipality','Khumbu Pasanglhamu Rural Municipality','Likhu Pike Rural Municipality','Mahakulung Rural Municipality','Necha Salyan Rural Municipality','Sotang Rural Municipality','Thulung Dudhkoshi Rural Municipality'],
  'Okhaldhunga'   => ['Siddhicharan Municipality','Champadevi Rural Municipality','Chisankhugadhi Rural Municipality','Khijidemba Rural Municipality','Likhu Rural Municipality','Manebhanjyang Rural Municipality','Molung Rural Municipality','Sunkoshi Rural Municipality'],
  'Khotang'       => ['Diktel Rupakot Majhuwagadhi Municipality','Aiselukharka Rural Municipality','Barahapokhari Rural Municipality','Halesi Tuwachung Municipality','Jantedhunga Rural Municipality','Kepilasgadhi Rural Municipality','Khotehang Rural Municipality','Lamidanda Rural Municipality','Rawabesi Rural Municipality','Sakela Rural Municipality'],
  'Udayapur'      => ['Triyuga Municipality','Belaka Municipality','Chaudandigadhi Municipality','Katari Municipality','Rautamai Rural Municipality','Sunkoshi Rural Municipality','Tapli Rural Municipality','Udayapurgadhi Rural Municipality'],
  'Morang'        => ['Biratnagar Metropolitan City','Urlabari Municipality','Rangeli Municipality','Letang Municipality','Sundarharaicha Municipality','Kerabari Municipality','Pathari Shanishchare Municipality','Belbari Municipality','Jahada Rural Municipality','Kanepokhari Rural Municipality','Katahari Rural Municipality','Gramthan Rural Municipality','Budhiganga Rural Municipality','Dhanpalthan Rural Municipality'],
  'Sunsari'       => ['Inaruwa Municipality','Dharan Sub-metropolitan City','Duhabi Municipality','Itahari Sub-metropolitan City','Barahachhetra Municipality','Ramdhuni Municipality','Barju Rural Municipality','Bhokraha Narsingh Rural Municipality','Dewanganj Rural Municipality','Gadhi Rural Municipality','Harinagar Rural Municipality','Koshi Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   2. MADHESH PROVINCE
═══════════════════════════════════════════════ */
'Madhesh' => [
  'Saptari'   => ['Rajbiraj Municipality','Bodebarsain Rural Municipality','Balan-Bihul Rural Municipality','Bisrauta Rural Municipality','Chhinnamasta Rural Municipality','Dakneshwori Rural Municipality','Hanumannagar Kankalini Municipality','Kanchanrup Municipality','Khadak Rural Municipality','Mahadewa Rural Municipality','Rupani Rural Municipality','Saptakoshi Rural Municipality','Shambhunath Municipality','Surunga Municipality','Tilathi Koiladi Rural Municipality','Agnisaira Krishnasavaran Rural Municipality'],
  'Siraha'    => ['Lahan Municipality','Golbazar Municipality','Karjanha Municipality','Mirchaiya Municipality','Siraha Municipality','Sukhipur Municipality','Arnama Rural Municipality','Aurahi Rural Municipality','Bariyarpatti Rural Municipality','Bishnupur Rural Municipality','Bhagawanpur Rural Municipality','Chorebarahi Rural Municipality','Kalyanpur Rural Municipality','Laganbesi Rural Municipality','Mahadewa Rural Municipality','Naraha Rural Municipality','Nawarajpur Rural Municipality','Shyamaprasad Rural Municipality','Dhangadhimai Municipality'],
  'Dhanusha'  => ['Janakpurdham Sub-metropolitan City','Hansapur Municipality','Dhanauji Municipality','Bideha Municipality','Mithila Municipality','Mithila Bihari Municipality','Sabaila Municipality','Shahidnagar Municipality','Aurahi Rural Municipality','Bateshwar Rural Municipality','Dhanauji Rural Municipality','Ganeshman Charnath Municipality','Janak Nandini Rural Municipality','Kamala Rural Municipality','Lakshminiya Rural Municipality','Mukhiyapatti Musharniya Rural Municipality','Nagarain Municipality','Samsara Rural Municipality'],
  'Mahottari' => ['Jaleshwar Municipality','Bardibas Municipality','Bhangaha Municipality','Gaushala Municipality','Loharpatti Municipality','Manra Siswa Rural Municipality','Matihani Municipality','Pipra Rural Municipality','Ramgopalpur Municipality','Samsi Rural Municipality','Sarathi Rural Municipality','Sonama Rural Municipality'],
  'Sarlahi'   => ['Barahathawa Municipality','Bagmati Municipality','Brahampuri Municipality','Chandranagar Rural Municipality','Dhankaul Rural Municipality','Godaita Municipality','Haripurwa Municipality','Haripur Municipality','Harion Municipality','Ishworpur Municipality','Kabilasi Municipality','Lalbandi Municipality','Malangawa Municipality','Parsa Rural Municipality','Ramnagar Rural Municipality'],
  'Rautahat'  => ['Baudhimai Municipality','Brindaban Municipality','Chandrapur Municipality','Dewahi Gonahi Municipality','Durga Bhagwati Rural Municipality','Gadhimai Municipality','Garuda Municipality','Gaur Municipality','Gujara Rural Municipality','Ishanath Municipality','Katahariya Municipality','Maulapur Municipality','Paroha Municipality','Phatuwabijayapur Municipality','Rajdevi Municipality','Rajpur Rural Municipality','Yamunamai Municipality'],
  'Bara'      => ['Kalaiya Sub-metropolitan City','Simraungadh Municipality','Nijgadh Municipality','Jitpur Simara Sub-metropolitan City','Adarsha Kotwal Rural Municipality','Bara Rural Municipality','Devtal Rural Municipality','Feta Rural Municipality','Karaiyamai Rural Municipality','Kolhabi Rural Municipality','Mahagadhimai Municipality','Pachirauta Municipality','Parawanipur Rural Municipality','Prasauni Rural Municipality','Suwarna Rural Municipality','Bishrampur Rural Municipality'],
  'Parsa'     => ['Birgunj Metropolitan City','Pokhariya Municipality','Parsagadhi Municipality','Bahudarmai Municipality','Bind Basini Rural Municipality','Chhipaharmai Rural Municipality','Dhobinikuwa Rural Municipality','Jagarnathpur Rural Municipality','Jirabhawani Rural Municipality','Kalikamai Rural Municipality','Pakaha Mainpur Rural Municipality','Paterwasugauli Rural Municipality','Sakhuwa Prasauni Rural Municipality','Thori Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   3. BAGMATI PROVINCE
═══════════════════════════════════════════════ */
'Bagmati' => [
  'Kathmandu'      => ['Kathmandu Metropolitan City','Kageshwori Manahara Municipality','Chandragiri Municipality','Dakshinkali Municipality','Gokarneshwar Municipality','Kirtipur Municipality','Nagarjun Municipality','Tarakeshwar Municipality','Tokha Municipality','Budhanilkantha Municipality','Shankharapur Municipality'],
  'Bhaktapur'      => ['Bhaktapur Municipality','Changunarayan Municipality','Madhyapur Thimi Municipality','Suryabinayak Municipality'],
  'Lalitpur'       => ['Lalitpur Metropolitan City','Godawari Municipality','Lalitpur Metropolitan City','Mahalaxmi Municipality','Konjyosom Rural Municipality'],
  'Kavrepalanchok' => ['Banepa Municipality','Dhulikhel Municipality','Mandan Deupur Municipality','Namobuddha Municipality','Panauti Municipality','Panchkhal Municipality','Temal Rural Municipality','Bethanchok Rural Municipality','Bhumlu Rural Municipality','Khanikhola Rural Municipality','Mahabharat Rural Municipality','Roshi Rural Municipality'],
  'Sindhupalchok'  => ['Chautara Sangachokgadhi Municipality','Bahrabise Municipality','Barhabise Municipality','Bhotekoshi Rural Municipality','Helambu Rural Municipality','Indrawati Rural Municipality','Jugal Rural Municipality','Lisankhu Pakhar Rural Municipality','Melamchi Municipality','Sunkoshi Rural Municipality','Tripurasundari Rural Municipality'],
  'Dolakha'        => ['Bhimeshwar Municipality','Jiri Municipality','Kalinchok Rural Municipality','Bigu Rural Municipality','Baiteshwar Rural Municipality','Gaurishankar Rural Municipality','Melung Rural Municipality','Sailung Rural Municipality','Tamakoshi Rural Municipality','Viku Rural Municipality'],
  'Rasuwa'         => ['Kalika Rural Municipality','Gosaikunda Rural Municipality','Naukunda Rural Municipality','Uttargaya Rural Municipality','Aamachhodingmo Rural Municipality'],
  'Nuwakot'        => ['Bidur Municipality','Belkotgadhi Municipality','Dupcheshwar Rural Municipality','Kakani Rural Municipality','Kispang Rural Municipality','Likha Rural Municipality','Meghang Rural Municipality','Myagang Rural Municipality','Panchakanya Rural Municipality','Shivapuri Rural Municipality','Suryagadhi Rural Municipality','Tadi Rural Municipality','Tarkeshwar Rural Municipality'],
  'Dhading'        => ['Nilkantha Municipality','Benighat Rorang Rural Municipality','Gajuri Rural Municipality','Galchi Rural Municipality','Gangajamuna Rural Municipality','Jwalamukhi Rural Municipality','Khaniyabas Rural Municipality','Netrawati Dabjong Rural Municipality','Rubi Valley Rural Municipality','Siddhalek Rural Municipality','Thakre Rural Municipality','Tripura Sundari Rural Municipality'],
  'Makwanpur'      => ['Hetauda Sub-metropolitan City','Bhimphedi Rural Municipality','Bakaiya Rural Municipality','Bagmati Rural Municipality','Indrasarowar Rural Municipality','Kailash Rural Municipality','Manahari Rural Municipality','Raksirang Rural Municipality','Thaha Municipality'],
  'Chitwan'        => ['Bharatpur Metropolitan City','Ratnanagar Municipality','Bharatpur Metropolitan City','Ichchhakamana Rural Municipality','Kalika Municipality','Khairahani Municipality','Madi Municipality','Rapti Municipality'],
  'Sindhuli'       => ['Kamalamai Municipality','Dudhauli Municipality','Golanjor Rural Municipality','Ghyanglekh Rural Municipality','Hariharpurgadhi Rural Municipality','Marin Rural Municipality','Phikkal Rural Municipality','Sunkoshi Rural Municipality','Tinpatan Rural Municipality'],
  'Ramechhap'      => ['Manthali Municipality','Ramechhap Municipality','Doramba Rural Municipality','Gokulganga Rural Municipality','Khandadevi Rural Municipality','Likhu Tamakoshi Rural Municipality','Sunapati Rural Municipality','Umakunda Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   4. GANDAKI PROVINCE
═══════════════════════════════════════════════ */
'Gandaki' => [
  'Kaski'    => ['Pokhara Metropolitan City','Annapurna Rural Municipality','Machhapuchchhre Rural Municipality','Madi Rural Municipality','Rupa Rural Municipality'],
  'Lamjung'  => ['Besisahar Municipality','Dordi Rural Municipality','Dudhpokhari Rural Municipality','Kwhlosothar Rural Municipality','Madhyanepal Rural Municipality','Marsyangdi Rural Municipality','Rainas Municipality','Sundarbazar Municipality'],
  'Tanahu'   => ['Bhimad Municipality','Byas Municipality','Devghat Rural Municipality','Ghiring Rural Municipality','Rishing Rural Municipality','Shuklagandaki Municipality','Anbukhaireni Rural Municipality','Bandipur Rural Municipality','Bhanu Municipality','Myagde Rural Municipality'],
  'Gorkha'   => ['Gorkha Municipality','Arughat Rural Municipality','Aarughat Rural Municipality','Barpak Sulikot Rural Municipality','Bhimsenthapa Rural Municipality','Dharche Rural Municipality','Gandaki Rural Municipality','Palungtar Municipality','Siranchok Rural Municipality','Tsum Nubri Rural Municipality'],
  'Syangja'  => ['Waling Municipality','Biruwa Municipality','Aandhikhola Rural Municipality','Arjunchaupari Rural Municipality','Bhirkot Municipality','Chapakot Municipality','Galyang Municipality','Kaligandaki Rural Municipality','Phedikhola Rural Municipality','Putalibazar Municipality'],
  'Parbat'   => ['Kushma Municipality','Paiyun Rural Municipality','Jaljala Rural Municipality','Mahashila Rural Municipality','Modi Rural Municipality','Phakel Rural Municipality'],
  'Baglung'  => ['Baglung Municipality','Badigad Rural Municipality','Bareng Rural Municipality','Dhorpatan Municipality','Galkot Municipality','Jaimini Municipality','Kathekhola Rural Municipality','Nisikhola Rural Municipality','Taman Khola Rural Municipality','Tarakhola Rural Municipality'],
  'Myagdi'   => ['Beni Municipality','Annapurna Rural Municipality','Dhaulagiri Rural Municipality','Mangala Rural Municipality','Malika Rural Municipality','Raghuganga Rural Municipality'],
  'Nawalpur' => ['Kawasoti Municipality','Bulingtar Rural Municipality','Binayee Tribeni Rural Municipality','Gaindakot Municipality','Hupsekot Municipality','Madhyabindu Municipality','Palhi Nandan Rural Municipality','Pratappur Rural Municipality'],
  'Palpa'    => ['Tansen Municipality','Mathagadhi Rural Municipality','Nisdi Rural Municipality','Purbakhola Rural Municipality','Rainadevi Chhahara Rural Municipality','Rambha Rural Municipality','Rampur Municipality','Ribdikot Rural Municipality','Tinau Rural Municipality'],
  'Manang'   => ['Chame Rural Municipality','Narphu Rural Municipality','Neshyang Rural Municipality','Narpa Bhumi Rural Municipality'],
  'Mustang'  => ['Lomanthang Rural Municipality','Lo-Ghekar Damodarkunda Rural Municipality','Thasang Rural Municipality','Varagung Muktichhetra Rural Municipality','Waragung Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   5. LUMBINI PROVINCE
═══════════════════════════════════════════════ */
'Lumbini' => [
  'Rupandehi'       => ['Butwal Sub-metropolitan City','Devdaha Municipality','Gaidahawa Rural Municipality','Kotahimai Rural Municipality','Lumbini Sanskriti Municipality','Marchawari Rural Municipality','Mayadevi Rural Municipality','Omsatiya Rural Municipality','Rohini Rural Municipality','Sammarimai Rural Municipality','Sainamaina Municipality','Siyari Rural Municipality','Sudhdhodhan Rural Municipality','Tillotama Municipality'],
  'Kapilvastu'      => ['Kapilvastu Municipality','Banganga Municipality','Bijaynagar Rural Municipality','Buddhabhumi Municipality','Krishnanagar Municipality','Maharajganj Municipality','Motipur Rural Municipality','Shivaraj Municipality','Suddhodhan Rural Municipality','Yashodhara Rural Municipality'],
  'Arghakhanchi'    => ['Sandhikharka Municipality','Bhumekasthan Municipality','Chhatradev Rural Municipality','Malarani Rural Municipality','Panini Rural Municipality','Sitganga Municipality'],
  'Gulmi'           => ['Resunga Municipality','Musikot Municipality','Chandrakot Rural Municipality','Chatrakot Rural Municipality','Dhurkot Rural Municipality','Gulmi Darbar Rural Municipality','Ishma Rural Municipality','Kaligandaki Rural Municipality','Madane Rural Municipality','Malika Rural Municipality','Ruru Rural Municipality','Satyawati Rural Municipality'],
  'Palpa'           => ['Tansen Municipality'],
  'Dang'            => ['Tulsipur Sub-metropolitan City','Ghorahi Sub-metropolitan City','Banglachuli Rural Municipality','Babai Rural Municipality','Dangisharan Rural Municipality','Dhangadhi Kailali Municipality','Gadhawa Rural Municipality','Lalmatiya Rural Municipality','Rajpur Rural Municipality','Rapti Rural Municipality','Shantinagar Rural Municipality'],
  'Banke'           => ['Nepalgunj Sub-metropolitan City','Duduwa Rural Municipality','Janki Rural Municipality','Khajura Rural Municipality','Kohalpur Municipality','Narainapur Rural Municipality','Rapti Sonari Rural Municipality'],
  'Bardiya'         => ['Gulariya Municipality','Barbardiya Municipality','Bansagadhi Municipality','Badhaiyatal Rural Municipality','Geruwa Rural Municipality','Madhuwan Municipality','Rajapur Municipality','Thakurbaba Municipality'],
  'Pyuthan'         => ['Pyuthan Municipality','Gaumukhi Rural Municipality','Jhimruk Rural Municipality','Lungri Rural Municipality','Mandavi Rural Municipality','Mallarani Rural Municipality','Naubahini Rural Municipality','Sarumarani Rural Municipality','Sworgadwari Municipality'],
  'Rolpa'           => ['Rolpa Municipality','Drangyang Rural Municipality','Gangadeva Rural Municipality','Liwang Municipality','Lungri Rural Municipality','Madi Rural Municipality','Pariwartan Rural Municipality','Runtigadhi Rural Municipality','Sunchhahari Rural Municipality','Thabang Rural Municipality','Tribeni Rural Municipality'],
  'Rukum (East)'    => ['Putha Uttarganga Rural Municipality','Sisne Rural Municipality'],
  'Nawalparasi (West)' => ['Sunwal Municipality','Baudikali Rural Municipality','Pratappur Rural Municipality','Palhi Nandan Rural Municipality','Sarawal Rural Municipality','Susta Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   6. KARNALI PROVINCE
═══════════════════════════════════════════════ */
'Karnali' => [
  'Surkhet'    => ['Birendranagar Municipality','Bheriganga Municipality','Gurbhakot Municipality','Bafikot Municipality','Chaukune Rural Municipality','Chingad Rural Municipality','Kunathari Rural Municipality','Lekbesi Municipality','Panchapuri Municipality','Simta Rural Municipality'],
  'Dailekh'    => ['Narayan Municipality','Aathabis Municipality','Bhairabi Rural Municipality','Chamunda Bindrasaini Municipality','Dullu Municipality','Dungeshwar Rural Municipality','Gurans Rural Municipality','Mahawai Rural Municipality','Naumule Rural Municipality','Thantikandh Rural Municipality'],
  'Jajarkot'   => ['Bheri Municipality','Barekot Rural Municipality','Chhedagad Municipality','Junichande Rural Municipality','Kuse Rural Municipality','Nalgad Municipality','Shivalaya Rural Municipality','Tribeni Rural Municipality'],
  'Salyan'     => ['Sharada Municipality','Bagchaur Municipality','Bagchaur Rural Municipality','Bangad Kupinde Municipality','Chhatreshwari Rural Municipality','Darma Rural Municipality','Kalimati Rural Municipality','Kumakh Rural Municipality','Siddha Kumakh Rural Municipality','Triveni Rural Municipality'],
  'Dolpa'      => ['Thuli Bheri Municipality','Chharka Tangsong Rural Municipality','Dolpo Buddha Rural Municipality','Jagdulla Rural Municipality','Kaike Rural Municipality','Mudkechula Rural Municipality','She Phoksundo Rural Municipality','Tripurasundari Municipality'],
  'Mugu'       => ['Chhayanath Rara Municipality','Khatyad Rural Municipality','Mugum Karmarong Rural Municipality','Soru Rural Municipality'],
  'Humla'      => ['Simkot Rural Municipality','Adanchuli Rural Municipality','Chankheli Rural Municipality','Kharpunath Rural Municipality','Namkha Rural Municipality','Sarkegad Rural Municipality','Tanjakot Rural Municipality'],
  'Kalikot'    => ['Khandachakra Municipality','Mahawai Rural Municipality','Narayanaswargadhi Municipality','Pachaljharana Rural Municipality','Palata Rural Municipality','Raskot Municipality','Sanni Triveni Rural Municipality','Tibrikot Municipality'],
  'Jumla'      => ['Chandannath Municipality','Patarasi Rural Municipality','Guthichaur Rural Municipality','Hima Rural Municipality','Kankasundari Rural Municipality','Sinja Rural Municipality','Tila Rural Municipality','Tatopani Rural Municipality'],
  'Rukum (West)' => ['Aathabiskot Municipality','Banfikot Rural Municipality','Bhume Rural Municipality','Chaurjahari Municipality','Maikot Rural Municipality','Musikot Municipality','Sanibheri Rural Municipality','Sani Bheri Rural Municipality','Triveni Rural Municipality'],
],

/* ═══════════════════════════════════════════════
   7. SUDURPASHCHIM PROVINCE
═══════════════════════════════════════════════ */
'Sudurpashchim' => [
  'Kailali'    => ['Dhangadhi Sub-metropolitan City','Ghodaghodi Municipality','Godawari Municipality','Jorayal Rural Municipality','Kailari Rural Municipality','Lamki Chuha Municipality','Mohanyal Rural Municipality','Phatepur Rural Municipality','Ramaroshan Rural Municipality','Shivapur Municipality','Tikapur Municipality'],
  'Kanchanpur' => ['Bhimdatta Municipality','Belauri Municipality','Bedkot Municipality','Krishnapur Municipality','Laljhadi Rural Municipality','Mahakali Municipality','Punarbas Municipality','Shuklaphanta Municipality'],
  'Dadeldhura' => ['Amargadhi Municipality','Aalital Rural Municipality','Ajayameru Rural Municipality','Bhageshwar Rural Municipality','Gangapur Rural Municipality','Ganyapdhura Rural Municipality','Navadurga Rural Municipality','Parashuram Municipality'],
  'Baitadi'    => ['Dasharathchand Municipality','Melauli Municipality','Patan Municipality','Purchaudi Municipality','Surnaya Rural Municipality','Dilasaini Rural Municipality','Dogdakedar Rural Municipality','Sigas Rural Municipality','Shivanath Rural Municipality','Pancheshwar Rural Municipality'],
  'Darchula'   => ['Shailyashikhar Municipality','Naugad Rural Municipality','Byans Rural Municipality','Duhun Rural Municipality','Lekam Rural Municipality','Marma Rural Municipality','Malikarjun Rural Municipality'],
  'Bajhang'    => ['Jayaprithvi Municipality','Bungal Municipality','Chhabis Pathibhera Rural Municipality','Chhededaha Rural Municipality','Durgathali Rural Municipality','Kanda Rural Municipality','Kedarsyu Rural Municipality','Khaptad Chhanna Rural Municipality','Masta Rural Municipality','Saijha Rural Municipality','Surma Rural Municipality','Thalara Rural Municipality','Talkot Municipality','Bithadchir Rural Municipality'],
  'Bajura'     => ['Badimalika Municipality','Budhiganga Municipality','Budinanda Municipality','Gaumul Rural Municipality','Himali Rural Municipality','Jagannath Rural Municipality','Kuldevta Rural Municipality','Pandav Gupha Rural Municipality','Swamikartik Khapar Rural Municipality','Triveni Rural Municipality'],
  'Achham'     => ['Mangalsen Municipality','Bannigadhi Jayagad Rural Municipality','Chaurpati Rural Municipality','Dhakari Rural Municipality','Kamalbazar Municipality','Mellekh Rural Municipality','Panchadeval Binayak Municipality','Ramaroshan Rural Municipality','Sanphebagar Municipality','Turmakhand Rural Municipality'],
],

    ]; // end $data
    return $data;
}
