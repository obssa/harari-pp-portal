<?php

namespace App\Domain\Content\Support;

/**
 * Afaan Oromo drafts of the legal pages (ADR-0043); doubtful wording is
 * listed in docs/translations-review.md.
 */
final class LegalPagesOm
{
    private const DRAFT = 'Wixinee gamaaggama seeraaf dhiyaate: barreeffamni kun hanga ammaatti waajjira damee biratti hin raggaafamne, jijjiiramuus ni danda\'a.';

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    public static function privacy(): array
    {
        return [
            'title' => 'Imaammata dhuunfaa',
            'excerpt' => 'Marsariitiin kun waa\'ee keessan maal akka sassaabu, maaliif, hammam akka qabatu, fi garagalcha ykn haqamuu akkamitti akka gaafattan.',
            'draft' => self::DRAFT,
            'sections' => [
                ['Kan itti gaafatamu', [
                    'Marsariitiin kun Paartii Badhaadhinaa Waajjira Damee Naannoo Harariitiin ("waajjira damee") bulfama. Fuula quunnamtii ykn teessoo imeelii fuula hundaa jalatti argamuun nu argachuu dandeessu.',
                ]],
                ['Maal akka sassaabnuu fi maaliif', [
                    'Galmee sagantaa: bakka keessan bulchuuf, tikeetii keessan erguuf fi sagantaa isin yaadachiisuuf maqaa keessanii fi lakkoofsa bilbilaa ykn teessoo imeelii keessan ni sassaabna. Sagantaa booda guyyoota 180 keessatti ni haqamu.',
                    'Ergaawwanii fi gaaffilee: deebii isinii kennuuf maqaa keessan, odeeffannoo quunnamtii keessan, deebiiwwan keessanii fi faayilii isin walitti maxxantan ni sassaabna. Dhimmichi erga cufamee booda waggaa lama keessatti ni haqamu.',
                    'Daawwannaa: fuulli yeroo meeqa akka banames kuukii malee fi odeeffannoo isin adda baasu tokko malee lakkoofna: fuula, afaan isaa, marsariitii irraa dhuftan, gosa meeshaa fi biyya qofa. Biraawuzarri akka hin hordofamne gaafatan hin lakkaa\'aman. Tajaajila beeksisaa ykn hordoffii hin fayyadamnu.',
                    'Nageenya: dhiyeessaan tajaajila keessummeessaa keenyaa fi Cloudflare marsariitii dhiyeessuu fi haleellaa irraa eeguuf odeeffannoo teeknikaa kan akka teessoo IP ni keessummeessu. Unkaaleen namoota sagantaalee ofumaan hojjetan irraa adda baasuuf Cloudflare Turnstile fayyadamuu danda\'u.',
                    'Viidiyoowwanii fi kaartaaleen YouTube (haala dhuunfaa cimsateen) fi OpenStreetMap irraa kan fe\'aman yeroo taphachiisuu ykn agarsiisuu filattan qofa.',
                ]],
                ['Bu\'uura seeraa keenya', [
                    'Daataa keessan kan fayyadamnu hayyama unka tokkoon tokkoon isaa irratti kennitaniin, akkasumas hojii waajjira damee ummata beeksisuu fi tajaajiluu raawwachuuf, Labsii Eegumsa Odeeffannoo Dhuunfaa Lakk. 1321/2024 waliin kan walsimu.',
                ]],
                ['Daataa keessan eenyutu arga', [
                    'Hojjettoota waajjira damee fi waajjiraalee isaa kanneen hojiin isaanii barbaachisu qofa, fakkeenyaaf waajjira gaaffii keessan keessummeessu. Daataa keessan hin gurgurru; beeksisaafis itti hin fayyadamnu. Dhiyeessitoonni tajaajila keessummeessaa, imeelii fi ergaa gabaabaa keenyaa nuuf qofa keessummeessu.',
                ]],
                ['Akkamitti akka eegnu', [
                    'Maqaawwanii fi odeeffannoon quunnamtii iccitiin eegamanii kuufamu; akkaawuntiiwwan hojjettootaa seensa sadarkaa lamaa fayyadamu; seensi hundi ni galmaa\'a; kuusaan hambaas iccitiin eegamee guyyoota 30 tura.',
                ]],
                ['Mirgoota keessan', [
                    'Waan waa\'ee keessan qabnu gaafachuu, akka sirraa\'u gaafachuu, akka haqamu gaafachuu fi yeroo kamiyyuu hayyama keessan deebifachuuf mirga qabdu. Teessoo imeelii ykn lakkoofsa bilbilaa itti fayyadamtaniin fuula quunnamtii irratti nuuf barreessaa. Hanga dandeenyetti dafnee fi yeroo seerri murteessu keessatti deebii ni kennina.',
                ]],
                ['Kuukii', [
                    'Marsariitiin afaan isin filattan kuukii xiqqaa keessatti yaadata. Fuulonni unka qaban yeroo unkicha guuttan kuukii yeroo seensaa fayyadamu. Beeksisaaf ykn hordoffiif kuukii hin kaa\'innu.',
                ]],
                ['Jijjiirama', [
                    'Imaammanni kun yeroo jijjiiramu, barreeffamni haaraan guyyaa isaa waliin fuula kana irratti ni maxxanfama.',
                ]],
            ],
        ];
    }

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    public static function terms(): array
    {
        return [
            'title' => 'Haalota itti fayyadamaa',
            'excerpt' => 'Seerota marsariitii kanaa fi qabiyyee isaa itti fayyadamuu.',
            'draft' => self::DRAFT,
            'sections' => [
                ['Waa\'ee marsariitii kanaa', [
                    'Kun marsariitii Paartii Badhaadhinaa Waajjira Damee Naannoo Harariiti. Itti fayyadamuun keessan haalota kana fudhachuu keessan agarsiisa.',
                ]],
                ['Qabiyyee keenya itti fayyadamuu', [
                    'Waajjira damee akka madditti yoo caqasattan, gara isa jalqabaatti yoo walqabsiiftanii fi hiika isaa yoo hin jijjiirre oduu, ibsaa fi sanadoota keenya qooddachuu fi caqasuu dandeessu. Suuraaleen fi sanadoonni tokko tokko kan warra biraati; yaadannoon isaanii kana ibsa.',
                ]],
                ['Sirrummaa', [
                    'Odeeffannoon sirrii fi yeroo isaa kan eeggate akka ta\'uuf ni hojjenna. Sanadni marsariitii kana irra jiru sanada jalqabaa mallattaa\'e irraa yoo adda ta\'e, kan jalqabaatu hojiirra oola.',
                ]],
                ['Ergaawwan keessan', [
                    'Maaloo kabajaan barreessaa. Ergaawwan arrabsoo qaban, seeraan ala ta\'an ykn odeeffannoo dhuunfaa namoota biroo qoodan deebisuu ykn maxxansuu dhiisuu dandeenya.',
                ]],
                ['Marsariitota biroo', [
                    'Liinkiiwwan gara marsariitota biraatti geessan mijataa keessaniif kennaman; qabiyyee isaaniif itti hin gaafatamnu.',
                ]],
                ['Argamuu tajaajilaa', [
                    'Kutaalee marsariitii fakkeenyaaf suphaaf jijjiiruu, addaan kutuu ykn dhaabuu dandeenya.',
                ]],
                ['Seera hojiirra oolu', [
                    'Haalonni kun seerota Rippaablika Federaalawaa Dimokraatawaa Itiyoophiyaa fi Mootummaa Naannoo Harariitiin bulu.',
                ]],
                ['Quunnamtii', [
                    'Gaaffii waa\'ee haalota kanaa fuula quunnamtii irratti nuuf ergaa.',
                ]],
            ],
        ];
    }

    /**
     * @return array{title: string, excerpt: string, draft: string, sections: list<array{0: string, 1: list<string>}>}
     */
    public static function accessibility(): array
    {
        return [
            'title' => 'Ibsa dhaqqabummaa',
            'excerpt' => 'Marsariitiin kun akka nama hundaaf fayyadu waan goonu, waan hanga ammaatti hin sirraa\'inii fi akkamitti akka nutti himtan.',
            'draft' => self::DRAFT,
            'sections' => [
                ['Kaayyoo keenya', [
                    'Namni kamiyyuu dandeettii, meeshaa ykn afaan isaa osoo hin ilaalin marsariitii kana akka fayyadamu barbaanna. Qajeelfama Dhaqqabummaa Qabiyyee Weebii (WCAG) 2.2 sadarkaa AA guutuuf ni hojjenna.',
                ]],
                ['Waan goonu', [
                    'Jijjiiramni hundi osoo hin maxxanfamin dura biraawuzara dhugaa keessatti rakkoo dhaqqabummaaf ofumaan qoratama. Fuulonni kiiboordii qofaan hojjetu, liinkii gara qabiyyeetti darbu qabu, mallattoon xiyyeeffannaas ni mul\'ata.',
                    'Barreeffamni hanga dachaa lamaatti guddachuu danda\'a; halluuwwan garaagarummaa gahaa qabu; meeshaan keessan yoo gaafate sochiin ni hir\'ata. Barreeffamni Itiyoopikii akka dubbistoonni iskiriinii sirriitti dubbisaniif afaan isaatiin mallattaa\'a.',
                    'Marsariitiin kun Afaan Ingiliffaa, Afaan Amaaraa fi Afaan Oromootiin argama; hiikkaan Afaan Harariis adeemsa irra jira.',
                ]],
                ['Waan hanga ammaatti hin sirraa\'in', [
                    'Sanadoonni durii tokko tokko, keessumaa faayiloonni PDF iskaan godhaman, dubbistoota iskiriiniitiin dubbifamuu hin danda\'an. Viidiyoowwan tokko tokko hanga ammaatti barreeffama jala hin qaban. Kutaaleen hiikkaa Harari tokko tokko ammas barreeffama Afaan Amaaraa ykn Ingiliffaa agarsiisu.',
                ]],
                ['Nutti himaa', [
                    'Marsariitii kana irratti wanti fayyadamuuf rakkisu yoo jiraate, ykn sanada bifa biraatiin yoo barbaaddan, fuula quunnamtii irratti nuuf barreessaa. Guyyoota hojii shan keessatti deebii kennuuf ni yaalla.',
                ]],
                ['Waa\'ee ibsa kanaa', [
                    'Ibsi kun yeroo marsariitiin jijjiiramuu fi yoo xiqqaate waggaatti al tokko ni gamaaggamama.',
                ]],
            ],
        ];
    }
}
