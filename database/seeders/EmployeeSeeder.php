<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds all 125 weekly employees from the Cathay Metal Corporation master list,
     * plus seeded accounts for HR Manager and Superadmin (no Office Admin — that
     * lives in OfficeAdminSeeder). Employee numbers follow the source document's
     * numbering scheme; positions are resolved by title.
     */
    public function run(): void
    {
        $superadminUser = User::where('email', 'superadmin@cameco.com')->first();
        $createdBy      = $superadminUser ? $superadminUser->id : 1;

        // ── Resolve departments ───────────────────────────────────────────
        $production = Department::where('code', 'PROD')->first();
        $hr         = Department::where('code', 'HR')->first();
        $it         = Department::where('code', 'IT')->first();
        $finance    = Department::where('code', 'FIN')->first();
        $operations = Department::where('code', 'OPS')->first();
        $sales      = Department::where('code', 'SALES')->first();

        $rollingMill1 = Department::firstOrCreate(
            ['code' => 'RM1'],
            ['name' => 'Rolling Mill 1', 'description' => 'Rolling Mill 1 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );
        $rollingMill2 = Department::firstOrCreate(
            ['code' => 'RM2'],
            ['name' => 'Rolling Mill 2', 'description' => 'Rolling Mill 2 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );
        $rollingMill3 = Department::firstOrCreate(
            ['code' => 'RM3'],
            ['name' => 'Rolling Mill 3', 'description' => 'Rolling Mill 3 under Production', 'is_active' => true, 'parent_id' => $production?->id]
        );

        // ── Resolve positions ─────────────────────────────────────────────
        $hrManager      = Position::where('title', 'HR Manager')->first();
        $hrSpecialist   = Position::where('title', 'HR Specialist')->first();
        $itManager      = Position::where('title', 'IT Manager')->first();
        $softwareDev    = Position::where('title', 'Software Developer')->first();
        $financeManager = Position::where('title', 'Finance Manager')->first();
        $accountant     = Position::where('title', 'Accountant')->first();
        $opsManager     = Position::where('title', 'Operations Manager')->first();
        $salesManager   = Position::where('title', 'Sales Manager')->first();
        $salesRep       = Position::where('title', 'Sales Representative')->first();
        $prodManager    = Position::where('title', 'Production Manager')->first();

        // Rolling Mill positions — prefixed titles avoid unique-title conflicts
        $rm1Worker  = Position::where('title', 'RM1 Production Worker')->first();
        $rm2Worker  = Position::where('title', 'RM2 Production Worker')->first();
        $rm3Worker  = Position::where('title', 'RM3 Production Worker')->first();

        // Plant-specific positions from the master list
        $supervisor    = Position::firstOrCreate(['title' => 'Supervisor'],    ['department_id' => $production?->id, 'level' => 'supervisor', 'is_active' => true]);
        $asstSupervisor = Position::firstOrCreate(['title' => 'Asst. Supervisor'], ['department_id' => $production?->id, 'level' => 'supervisor', 'is_active' => true]);
        $rollingMillCrew = Position::firstOrCreate(['title' => 'Rolling Mill Crew'], ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);
        $qualityClerk  = Position::firstOrCreate(['title' => 'Quality Clerk'],  ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);
        $stockroomClerk = Position::firstOrCreate(['title' => 'Stockroom Clerk'], ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);
        $machinist     = Position::firstOrCreate(['title' => 'Machinist'],      ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);
        $electrician   = Position::firstOrCreate(['title' => 'Electrician'],    ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);
        $companyDriver = Position::firstOrCreate(['title' => 'Company Driver'], ['department_id' => $operations?->id, 'level' => 'staff', 'is_active' => true]);
        $utility       = Position::firstOrCreate(['title' => 'Utility'],        ['department_id' => $production?->id, 'level' => 'staff', 'is_active' => true]);

        // Helper: resolve Position model from the master-list position string
        $resolvePosition = function (string $posStr) use (
            $supervisor, $asstSupervisor, $rollingMillCrew,
            $qualityClerk, $stockroomClerk, $machinist,
            $electrician, $companyDriver, $utility
        ): ?Position {
            return match (strtoupper(trim($posStr))) {
                'SUPERVISOR'        => $supervisor,
                'ASST. SUPERVISOR'  => $asstSupervisor,
                'ROLLING MILL CREW' => $rollingMillCrew,
                'QUALITY CLERK'     => $qualityClerk,
                'STOCKROOM CLERK'   => $stockroomClerk,
                'MACHINIST'         => $machinist,
                'ELECTRICIAN'       => $electrician,
                'COMPANY DRIVER'    => $companyDriver,
                'UTILITY'           => $utility,
                default             => $rollingMillCrew,
            };
        };

        // ── Master-list employees (all 125 from the document) ─────────────
        // Format: [last, first, middle, suffix, emp_no, dob, civil_status, date_hired,
        //          sss, pagibig, tin, philhealth, position_str, address]
        $masterList = [
            ['ADRIANO',       'ALEX',       'CASTILLO',    null,  '1319',    '1970-03-18', 'M', '2016-10-10', '34-2297501-1',  '1210-0349-1544',          '403-411-299', '2300-2482-9729', 'ROLLING MILL CREW',  'NO. 7587 L -1 , B-22 Swimming Pool St. Maligaya Park Subdivision Caloocan City.1422'],
            ['ALMAZAN',       'ORLANDO',    'PALATTAO',    null,  '100487',  '1962-10-24', 'M', '1993-12-07', '33-0313768-0',  '1060-0002-2324',          '146-698-524', '1905-0342-0876', 'SUPERVISOR',         'Purok 4, Mapulang Lupa, Valenzuela, Metro Manila. 1448'],
            ['ANGEL JR.',     'ERVIN',      'QUIDPUAN',    'Jr.', '2053',    '1985-10-08', 'M', '2018-02-23', '01-2751621-5',  '1212-1862-7295',          '344-504-285', '0520-1188-6922', 'ROLLING MILL CREW',  'Blk. 12, Lot 1, Tungkong Langit Corner Tatlong Hari ST., Lagro Subd. Novaliches, Q.C. 1118'],
            ['ANTIPUESTO',    'CHARLIE',    'TALINGTING',  null,  '100608',  '1973-07-03', 'M', '1994-05-25', '33-3860930-3',  '1060-0002-2370',          '170-047-217', '1905-0941-3901', 'ROLLING MILL CREW',  '199 P. Dela Cruz St. San Bartolome, Novaliches, Q.C. 1116'],
            ['AQUINO',        'EDGAR',      'GENES',       null,  '100360',  '1971-09-10', 'M', '1991-08-18', '33-1047862-0',  '1210-3556-7538',          '100-856-812', '1905-0342-1163', 'ROLLING MILL CREW',  'L16 B128 Area D Santolan St. Camarin, Caloocan City. 1422'],
            ['AQUINO',        'JOSE',       'CANOMAY',     null,  '100085',  '1961-11-12', 'M', '1985-01-25', '03-5979175-6',  '1060-0002-2401',          '100-856-838', '1905-0342-1198', 'ROLLING MILL CREW',  '157 P. Dela Cruz St. San Bartolome, Novaliches, Q.C. 1116'],
            ['ARLIGUE JR.',   'ROGELIO',    'PRADEL',      'Jr.', '1812',    '1980-09-16', 'M', '2015-08-10', '33-7160109-6',  '1060-0001-9725',          '250-923-234', '0305-0456-3509', 'ROLLING MILL CREW',  'Blk. 12, Lot 26, Phase M, Francisco Homes, Subd., San Jose del Monte, Bulacan. 3023'],
            ['AVENIDO',       'ABRAHAM',    'DOCDOC',      null,  '1471',    '1974-12-22', 'M', '2015-06-15', '33-4597833-1',  '1210-9715-2565',          '281-175-687', '1909-0345-1436', 'ROLLING MILL CREW',  'Blk. 31, Lot 10, Bougainvilla St., Maligaya Park Subd., Caloocan City. 1422'],
            ['BALIGUAT',      'VHIC ERICSON', '',          null,  '3003',    '1998-10-22', 'S', '2020-08-28', '34-7350149-2',  '1212-1739-9873',          '386-334-204', '0325-0994-0184', 'QUALITY CLERK',      'No. 119 P. dela Cruz St. San Bartolome, Novaliches, Q.C. 1116'],
            ['BARROGA',       'ERIC',       'LAJERA',      null,  '1344',    '1984-09-03', 'M', '2015-02-18', '33-8943363-8',  '1210-2453-6010',          '410-642-723', '0805-0878-3306', 'STOCKROOM CLERK',    'No. 8 Guyabano Road, Potero, Malabon City. 1475'],
            ['BASCON',        'ARNEL',      'OPIANA',      null,  '960040',  '1973-03-24', 'M', '1996-09-23', '33-2943821-1',  '1060-0004-3305',          '186-129-390', '1905-0342-1716', 'ROLLING MILL CREW',  'G-21 Horeshoe St., Zone 4, Signal Village, Taguig, Metro Manila. 1636'],
            ['BAYLOSIS',      'GLENN',      'VILLAFLORES', null,  '960053',  '1976-04-20', 'M', '1996-10-28', '33-2480676-7',  '1060-0002-2657',          '186-129-412', '1905-0342-1821', 'ROLLING MILL CREW',  '476 Dizon St., Nomar, San Bartolome, Novaliches, Q.C. 1116'],
            ['BAYOT',         'SONNY',      'VISAYA',      null,  '1813',    '1978-09-12', 'M', '2015-08-05', '33-4107155-9',  '1060-0001-3443',          '248-880-302', '1920-0683-7251', 'ROLLING MILL CREW',  'Phase V-A, Pkg. 2, Blk 3 Lot 83, Bagong Silang, Caloocan City. 1428'],
            ['BEDUYA',        'BENJAMIN',   'DIOLA',       null,  '200045',  '1965-06-18', 'M', '2000-08-05', '03-7962259-1',  '1210-3924-6808',          '111-117-150', '1905-2053-3978', 'ROLLING MILL CREW',  'No. 604 Bagbag, Quirino Highway, Novaliches, Q.C. 1116'],
            ['BELEN',         'ARWIN',      'BELLEN',      null,  '1726',    '1985-10-28', 'M', '2015-03-12', '34-2941026-5',  '1211-1558-3087',          '435-983-620', '0305-1023-0457', 'ROLLING MILL CREW',  'Brilliant View, Phase 2, Bagumbong, Caloocan City. 1421'],
            ['BERSALES',      'JOEFFREY',   'EGOT',        null,  '1621',    '1984-05-06', 'M', '2016-08-16', '10-0907159-2',  '1211-4576-1563',          '459-897-334', '2300-0917-0257', 'ROLLING MILL CREW',  'Phase 9, Pkg. 7-B, Blk 24 Lot 34, Bagong Silang, Caloocan City. 1428'],
            ['BURA-AY',       'RUSTOM',     'CORTEZ',      null,  '1354',    '1995-11-27', 'S', '2015-01-07', '34-4245222-6',  '1211-0960-5523',          '452-890-958', '0302-5508-0144', 'ROLLING MILL CREW',  'Blk 67 Lot 26, Riyal St., North Fairview, Q.C. 1121'],
            ['CANAYA',        'EDMER',      'LADROMA',     null,  '201335',  '1988-08-19', 'M', '2016-01-20', '34-0297830-0',  '1210-9806-1098',          '411-355-221', '0305-0703-7476', 'ROLLING MILL CREW',  'No. 136 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['CANTONJOS',     'JOVETH',     'ACUYONG',     null,  '1955',    '1998-01-27', 'S', '2017-05-15', '34-5889966-6',  '1211-6941-3429',          '331-262-839', '0302-5952-2346', 'ROLLING MILL CREW',  'Rivera Extension, Rockville Compound, San Bartolome, Novaliches, Q.C. 1116'],
            ['CARABUENA',     'ROBERT',     'LORCA',       null,  '1987',    '1980-04-07', 'S', '2018-06-25', '07-1720152-9',  '1211-8896-0595',          '714-880-990', '0302-6070-1483', 'ROLLING MILL CREW',  'No. 217 Magno St., Seminario Rd., Bagbag, Novaliches, Q.C. 1116'],
            ['CARIAGA',       'ERNAN',      'DANTES',      null,  '100462',  '1975-09-06', 'M', '1993-09-09', '33-3780570-0',  '1060-0002-2870',          '160-996-431', '1905-0342-2658', 'ROLLING MILL CREW',  'No. 175 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['COMILES',       'JOMAR',      'PILAGAN',     null,  '2089',    '1993-11-24', 'S', '2019-06-03', '01-2508594-2',  '1211-5454-8480',          '325-713-125', '0520-1547-8462', 'ROLLING MILL CREW',  'Blk. 4 Lot 28, Pitong Bahay St., Bukluran Maligaya Park Subd., Brgy. 177 Caloocan City. 1400'],
            ['CONCEPCION',    'ROMMEL',     'FRANCO',      null,  '100789',  '1975-07-18', 'M', '1995-10-19', '33-3855029-0',  '1060-0002-2935',          '173-754-438', '1905-0342-3042', 'ROLLING MILL CREW',  'Blk 1 Carreon Village, San Bartolome, Novaliches, Q.C. 1116'],
            ['CONTIGA',       'FREDDIE',    'AQUINO',      null,  '100011',  '1952-07-27', 'M', '1973-04-16', '03-2850197-7',  '1060-0002-2970',          '100-855-535', '1905-0941-5939', 'SUPERVISOR',         'No. 229 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['CONTIGA',       'RANDY',      'GERONIMO',    null,  '1941',    '1976-11-05', 'M', '2016-08-02', '33-6487764-6',  '1060-0000-8723',          '234-198-207', '0305-0090-4018', 'ROLLING MILL CREW',  'Blk 29 Lot 8, Dela Costa III, Phase 2, SJDM, Bulacan. 3023'],
            ['CUEVAS',        'EDWIN',      'PAZON',       null,  '100793',  '1973-10-15', 'M', '1995-04-09', '33-2528775-4',  '1060-0002-3011',          '173-754-445', '1905-0342-3328', 'ROLLING MILL CREW',  'Phase 1, Sec. 4, Blk. 27, Lot 13, Pabahay 2000, Muzon, San Jose del Monte, Bulacan. 3023'],
            ['CUNANAN',       'CHRISTOPHER','TEVES',       null,  '1756',    '1985-10-11', 'M', '2015-06-18', '34-2253193-4',  '1211-7389-4676',          '283-328-998', '0302-5152-7888', 'SUPERVISOR',         'No. 25 J.P. Rizal St., Dona Faustina Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['DALAYAT',       'JUNE MARCEL','LAIG',        null,  '200039',  '1971-06-20', 'M', '2000-07-08', '33-1535041-3',  '1210-7527-4883',          '148-909-368', '1905-2053-4249', 'ROLLING MILL CREW',  'Rivera Extension, Rockville I Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['DE GUZMAN',     'REYNALDO',   'BALDO',       null,  '100398',  '1967-02-02', 'M', '1992-09-04', '33-1688537-2',  '1060-0002-3100',          '157-073-984', '1905-0941-6404', 'ROLLING MILL CREW',  'Blk 7 Lot 3, Phase 2, Celina Homes, Camarin, Caloocan City. 1422'],
            ['DELA CRUZ',     'RONALD',     'URSUA',       null,  '100741',  '1975-10-27', 'M', '1995-01-21', '33-1764023-1',  '1060-0002-3134',          '176-362-777', '1905-0342-3646', 'ROLLING MILL CREW',  'Blk 2, Carreon Village, Holy Cross Road, San Bartolome, Novaliches, Q.C. 1116'],
            ['DIDA-AGON',     'ANDREW',     'PURACAN',     null,  '100366',  '1966-09-23', 'M', '1991-09-12', '33-1370479-9',  '1060-0002-3180',          '129-539-465', '1905-0342-3808', 'ROLLING MILL CREW',  'No. 12, Careon Subdivision, San Bartolome, Novaliches, Q.C. 1116'],
            ['DIEGO',         'FREDDIE',    'SIMON',       null,  '100323',  '1968-02-16', 'M', '1990-05-03', '33-0196782-3',  '1060-0002-3191',          '100-857-745', '1905-0342-3816', 'ROLLING MILL CREW',  'Blk 14 Lot 2, Dela Costa Homes IV, Graceville, San Jose del Monte Bulacan. 3023'],
            ['DOGUNA',        'GERRY',      'CHAVEZ',      null,  '100453',  '1966-08-17', 'M', '1993-08-13', '03-8203277-0',  '1060-0002-3200',          '160-996-510', '1905-0342-3859', 'ROLLING MILL CREW',  '779 St., Joseph Avenue, Tala, Caloocan City. 1427'],
            ['DOMINGO',       'SHERWIN',    'LUZONG',      null,  '10008',   '1972-12-15', 'M', '2001-01-15', '33-3623266-2',  '1060-0004-1942',          '215-907-362', '1905-0718-1744', 'SUPERVISOR',         'No. 62, Pink St., Odelco Subdivision, San Bartolome, Novaliches, Q.C. 1116'],
            ['DULLA',         'ADRYAN',     'SAMONTE',     null,  '1861',    '1995-09-18', 'S', '2016-08-16', '34-4018358-4',  '1211-3477-4518',          '456-758-588', '0305-1145-9326', 'ROLLING MILL CREW',  'No. 615 Interior O, Bagbag, Novaliches, Q.C.'],
            ['ELAMPARO',      'JOEL',       'AGUILO',      null,  '1727',    '1972-01-07', 'M', '2018-03-01', '33-1475196-1',  '1060-0000-7946',          '918-586-542', '0305-0022-2693', 'ROLLING MILL CREW',  'Rivera Extension Rockville 1 Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['ESCOLANO JR.',  'FRANCISCO',  'AUDITOR',     'Jr.', '1324',    '1969-09-05', 'M', '2013-11-16', '06-1258323-2',  '1210-0436-1235',          '403-412-561', '1220-0335-5398', 'ROLLING MILL CREW',  'No. 21 Florence Ville, Pasacola, Novaliches, Q.C. 1125'],
            ['ESTOPA',        'EUGENE',     'TOÑACO',      null,  '1596',    '1991-05-03', 'M', '2015-08-17', '34-1998127-0',  '1210-3077-9587',          '294-565-918', '0305-0670-2638', 'ROLLING MILL CREW',  'Rivera Extension, San Bartolome, Novaliches, Q.C. 1116'],
            ['ETE',           'EDUARDO',    'IBAÑEZ',      null,  '3047',    '1985-01-17', 'S', '2020-08-13', '33-9812741-4',  '1212-6565-0472',          '244-021-782', '0305-0126-7399', 'ROLLING MILL CREW',  'Blk. 64 Lot 34, Japan St., Harmony Hills Muzon, San Jose del Monte, Bulacan'],
            ['EVANGELISTA',   'JOSE BANIE', 'BALINGIT',    null,  '970056',  '1975-04-29', 'M', '1998-06-04', '02-1221761-0',  '1210-4577-2849',          '909-973-031', '1905-0342-4219', 'ROLLING MILL CREW',  '#9 Ramos Compound, San Bartolome, Novaliches, Q.C. 1116'],
            ['FERNANDEZ III', 'DOMINGO',    'HERRADURA',   'III', '1301',    '1985-03-26', 'M', '2016-01-04', '34-0455154-7',  '1210-2455-5274',          '294-547-502', '0902-5102-0875', 'ROLLING MILL CREW',  'No. 169 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['FLAVIANO',      'JAY',        'MARAPO',      null,  '1589',    '1979-12-18', 'M', '2014-07-17', '33-4794449-9',  '1060-0000-7392',          '230-870-415', '1909-0466-1191', 'ROLLING MILL CREW',  'Patsy St., Hacienda Caretas, Graceville, San Jose del Monte, Bulacan. 3023'],
            ['FLORES',        'VICENTE',    '',            null,  '3055',    '1958-09-10', 'M', '2020-11-03', '03-4938546-0',  '1060-0000-2418',          '122-927-983', '1905-0031-1251', 'SUPERVISOR',         'No. 96 Balubaran St., Malinta, Valenzuela City'],
            ['FRANCISCO JR.', 'ENRIQUE',    'MAGADIA',     'Jr.', '1555',    '1980-05-11', 'S', '2014-03-20', '33-6047847-6',  '1210-6366-3465',          '248-357-687', '0205-0069-8013', 'ROLLING MILL CREW',  'No. 164 Acme Road, San Bartolome, Novaliches, Q.C.'],
            ['GALAPATE',      'MAR',        'RELATIVO',    null,  '100590',  '1975-11-11', 'M', '1994-05-11', '33-3778190-5',  '1060-0002-3300',          '168-777-029', '1905-0342-4340', 'ROLLING MILL CREW',  'No. 138 Magsaysay Ave., Doña Faustina Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['GALDA JR.',     'FEDERICO',   'BALURAN',     'Jr.', '1776',    '1990-06-05', 'S', '2016-09-05', '34-4941265-4',  '1211-3649-2177',          '478-207-019', '0305-0695-5374', 'ROLLING MILL CREW',  'No. 30 Urbano St., Brgy. Bagbag, Novaliches, Q.C. 1116'],
            ['GARCIA',        'JOSE',       'MIRANDA',     null,  '100774',  '1964-06-17', 'M', '1995-03-06', '03-8167582-4',  '1060-0002-3323',          '173-754-461', '1905-0342-4456', 'SUPERVISOR',         'Lot 8, Road 40, Congress Village, Novaliches, Caloocan City. 1422'],
            ['GARCIA',        'RICHARD',    'CUARESMA',    null,  '1480',    '1987-10-04', 'S', '2015-07-22', '34-0707171-0',  '1210-9811-9813',          '290-134-479', '0305-0529-7381', 'ROLLING MILL CREW',  'No. 314 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['GARCIA',        'VALENTINO',  'MAGAOAY',     null,  '100160',  '1967-02-14', 'M', '1987-07-10', '03-9097580-3',  '1210-4636-4462',          '100-857-974', '1905-0342-4421', 'ROLLING MILL CREW',  'Blk 22, Lot 19, Dela Costa Homes IV, Graceville, San Jose del Monte, Bulacan. 3023'],
            ['GENOBISA',      'HANZEL MARK','PAYAC',       null,  '1838',    '1994-03-17', 'S', '2017-05-15', '34-4824526-2',  '1211-4758-8645',          '478-445-186', '0302-5800-7375', 'ROLLING MILL CREW',  'No. 103 Bana Compound, Doña Faustina Subdivision, San Bartolome, Novaliches, Q.C. 1116'],
            ['GILLAMAC',      'ALFREDO',    'LUNA',        null,  '100844',  '1968-07-28', 'M', '1998-05-19', '33-0908659-9',  '1060-0002-3356',          '179-468-452', '1905-0342-4561', 'ROLLING MILL CREW',  '791 Melon Road, Potrero Malabon. 1475'],
            ['GIPIT',         'REYNALDO',   'BALIGUEN',    null,  '100845',  '1969-08-20', 'M', '1995-11-03', '33-1045837-8',  '1210-2069-8202',          '179-468-460', '1905-0941-7478', 'ROLLING MILL CREW',  'Phase 10-A Package 4, Lot 17 Blk 58, Bagong Silang, Caloocan City. 1428'],
            ['GLODOVE',       'ALLAN JEFF', 'LIGNES',      null,  '1953',    '1991-04-16', 'S', '2016-09-06', '06-3281192-6',  '1211-7495-2770',          '331-011-929', '1220-1544-8723', 'ROLLING MILL CREW',  'No. 119 P. dela Cruz St. San Bartolome, Novaliches, Q.C. 1116'],
            ['GOLO',          'HILARIO',    'ESPENIDO',    null,  '990007',  '1967-04-21', 'M', '1999-05-06', '03-8933852-9',  '1060-0002-7818',          '111-117-465', '1905-0342-4634', 'ROLLING MILL CREW',  'No. 46 Mutya St., Interior Group A, Payatas B, Q.C. 1119'],
            ['GOMILAO',       'SILVINO',    'VINTIC',      null,  '100594',  '1970-03-08', 'M', '1994-05-14', '33-0582977-6',  '1060-0002-3380',          '130-997-347', '1905-0941-7540', 'ROLLING MILL CREW',  'No. 3-A Jordan Valley Village, Baesa, Novaliches, Q.C. 1106'],
            ['GUALBERTO',     'OSCAR',      'BARRAMEDA',   null,  '100530',  '1967-10-26', 'M', '1994-11-07', '03-9967534-0',  '1210-2312-9434',          '160-996-551', '1905-0342-4685', 'ROLLING MILL CREW',  'No. 12, T. Carreon St., Bagbag, Novaliches, Q.C. 1116'],
            ['HOMBRIA',       'RONNEL',     'IBAÑEZ',      null,  '1511',    '1986-08-27', 'M', '2013-08-03', '34-1200183-0',  '1090-0163-9177',          '429-550-181', '0205-0498-6889', 'SUPERVISOR',         'Blk. 19, Lot 32, Israel St., Harmony Hills I, Muzon, San Jose del Monte, Bulacan. 3023'],
            ['HOMEREZ',       'RHEY ANDREW','CALPIS',      null,  '1364',    '1989-11-30', 'S', '2016-07-02', '34-1130797-5',  '1210-0403-4029',          '262-150-521', '0805-0803-0650', 'ROLLING MILL CREW',  'E-88 Abbey Road, Bagbag, Novaliches, Q.C.'],
            ['HUFANA',        'EDWARD',     'RUBIO',       null,  '960059',  '1969-09-20', 'M', '1996-04-16', '33-0842601-1',  '1060-0002-3456',          '186-129-453', '1905-0342-4863', 'ROLLING MILL CREW',  'Blk. 3, Lot 35, North Point Subd., P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['JETAJOBE',      'ARNOLD',     'VERANO',      null,  '1908',    '1977-11-21', 'M', '2016-05-11', '33-2009618-6',  '1210-4675-3721',          '201-817-300', '1905-0760-1840', 'ROLLING MILL CREW',  'No. 21 Magno Subdivision, Brgy. Bagbag, Novaliches, Q.C. 1116'],
            ['KAUM',          'ROBERT',     'BARCELO',     null,  '100714',  '1963-01-03', 'M', '1994-10-05', '03-6236614-9',  '1060-0002-3479',          '172-192-118', '1905-0342-5037', 'ROLLING MILL CREW',  'No. 126-C, ACF Road, San Bartolome, Novaliches, Q.C. 1116'],
            ['LACHICA',       'NESLIE',     'AUSAN',       null,  '2066',    '1987-03-13', 'M', '2018-07-12', '07-2506103-6',  '1210-8419-1799',          '431-921-639', '0302-5416-9338', 'SUPERVISOR',         'B3, L23, Champaca St., Maligaya Park Subd., Brgy. Pasong Putik, Novaliches, Q.C. 1117'],
            ['LAFUENTE',      'MARK',       'BEJOSANO',    null,  '1945',    '1987-01-11', 'S', '2016-08-19', '34-1309034-5',  '1211-5929-6634',          '330-604-930', '0302-5982-9093', 'STOCKROOM CLERK',    'No. 165, Int.-O, Brgy. Bagbag, Novaliches, Q.C. 1116'],
            ['LAIÑO',         'BERNARDO',   'BERMUNDO',    null,  '100075',  '1960-12-10', 'M', '1985-01-08', '03-6574888-7',  '1060-0002-3480',          '100-858-072', '1905-0342-5088', 'ROLLING MILL CREW',  'No. 170 Atlas Road, San Bartolome, Novaliches, Q.C. 1116'],
            ['LAYNESA',       'FRANCISCO',  'TORMES',      null,  '100539',  '1972-01-15', 'M', '1994-03-03', '05-0463426-0',  '1060-0002-3512',          '168-777-037', '1905-0941-8032', 'ASST. SUPERVISOR',   'Lot 2-B, Makasiar Hoa-Kadalagahan St., Gulod, Novaliches, Q.C. 1117'],
            ['LAYNESA',       'FREDDIE',    'TORMES',      null,  '1328',    '1987-08-28', 'M', '2014-01-15', '34-2327186-6',  '1210-0531-4865',          '403-413-258', '0305-0774-0053', 'MACHINIST',          'No. 147 Kilyawan St., Sta. Monica, Novaliches, Q.C. 1117'],
            ['LEOPOLDO',      'JOHNNY',     'BALOLONG',    null,  '1948',    '1981-03-01', 'M', '2017-11-20', '33-7021384-1',  '1210-4242-3831',          '212-518-283', '0105-0010-0171', 'ROLLING MILL CREW',  'Phase 3, Dormitory Pasacola, Nagkaisang Nayon, Novaliches, Q.C. 1125'],
            ['LIGNES',        'LARRY',      'DELAVIN',     null,  '100139',  '1968-04-17', 'M', '1987-02-24', '03-8660669-4',  '1060-0002-3534',          '100-859-806', '1905-0342-5258', 'ROLLING MILL CREW',  '#119 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['LOSABIO',       'MELVIN',     'FULGENCIO',   null,  '1966',    '1979-05-18', 'M', '2016-10-13', '33-7266212-8',  '1211-1375-9743',          '223-713-808', '0105-0252-7393', 'ROLLING MILL CREW',  'No. 36 Golo St., Rockville I Subdivision, San Bartolome, Novaliches, Q.C. 1116'],
            ['LUBOSANA',      'ROMMEL',     'BARRAC',      null,  '1496',    '1978-03-24', 'M', '2015-07-20', '33-4108935-0',  '1211-0067-1291',          '210-573-070', '1908-9316-4638', 'ROLLING MILL CREW',  'No. 164 Acme Road, San Bartolome, Novaliches, Q.C.'],
            ['LUNA',          'MARK JOSEPH','GARBO',       null,  '1755',    '1993-07-24', 'M', '2015-06-16', '34-4524221-3',  '1211-1821-9603',          '478-270-040', '0302-5598-3624', 'ROLLING MILL CREW',  'Rivera Ext., Rockville Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['MACEDA',        'JOHN BOY',   'ARANETA',     null,  '1670',    '1984-08-03', 'S', '2015-02-16', '33-8515437-7',  '1210-5752-9681',          '269-979-956', '1909-0157-2821', 'ROLLING MILL CREW',  'Rivera Extension, Rockville I Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['MANANQUIL',     'VICTORINO',  'ROSALES',     null,  '1736',    '1967-10-14', 'M', '2015-04-06', '03-8303510-3',  '1211-4949-1821',          '294-355-884', '1908-9981-9873', 'MACHINIST',          'No. 603 Emerald St., Greenheights Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['MARCAIDA',      'CHESTER',    'BIEL',        null,  '2043',    '1990-12-05', 'S', '2017-10-03', '34-3114758-4',  '1211-3652-5800',          '341-054-563', '2200-0088-0604', 'ELECTRICIAN',        'No. 142 M. De Sotto St., Paso De Blas, Valenzuela City. 1442'],
            ['MASANGKAY',     'JOHN PAUL',  'JUSON',       null,  '2021',    '1997-07-03', 'S', '2017-11-20', '34-6447976-6',  '1211-8869-0921',          '333-765-551', '0302-6071-2981', 'UTILITY',            'No. 48, Kalinisan St., R.P., Brgy Gulod, Novaliches, Q.C. 1117'],
            ['MELCHOR',       'GERALD',     'SERRANO',     null,  '2090',    '1998-04-16', 'S', '2019-01-19', '34-7867622-5',  '1211-8571-2099',          '354-012-698', '0102-6359-8844', 'ROLLING MILL CREW',  'Purok 5, Tambakan 2, San Miguel, Pasig City. 1600'],
            ['MENDEZ',        'KENNEDY',    'MADIA',       null,  '1778',    '1993-05-19', 'S', '2015-07-15', '34-4441567-2',  '1211-3181-2339',          '478-272-331', '0302-5692-7566', 'ROLLING MILL CREW',  'No. 20 Jongs St., Goodwill Homes II, Bagbag, Novaliches, Q.C. 1116'],
            ['MENDOZA',       'NELSON',     'MACALINDONG', null,  '100732',  '1970-08-05', 'M', '1995-01-12', '04-0752841-5',  '1210-3872-6008',          '138-070-747', '1905-0342-5754', 'ROLLING MILL CREW',  'No. 38, ACF Subdivision, P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['MERCADO',       'JONNEL',     'PAMPOLA',     null,  '2040',    '1996-11-03', 'S', '2017-08-05', '34-6749329-9',  '1211-9876-9902',          '339-581-638', '0925-1777-9395', 'ROLLING MILL CREW',  '299 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['MONGCAL',       'DHAN LOUIE', 'DELA CRUZ',   null,  '2064',    '1992-10-13', 'S', '2018-07-03', '34-2797127-2',  '1212-2823-7279',          '344-819-086', '0320-0187-6626', 'UTILITY',            'Phase 8B, Pkg. 5, Blk 69 Lot 1, Bagong Silang, Caloocan City. 1400'],
            ['MORANTE',       'CEDIE',      'DE VERA',     null,  '1967',    '1993-06-21', 'S', '2016-10-14', '34-5730385-2',  '1211-8290-7172',          '332-090-252', '2105-0230-6450', 'ROLLING MILL CREW',  'No. 17 Little Grace Park, Malcahan, Meycauayan City, Bulacan. 3023'],
            ['MORANTE',       'RICHARD',    'DE VERA',     null,  '1700',    '1985-10-15', 'M', '2016-09-13', '33-8791939-0',  '1211-1231-4909',          '420-659-047', '2105-0056-2891', 'ROLLING MILL CREW',  'No. 17 Little Grace Park, Malkahan, Meycauayan City, Bulacan. 3013'],
            ['MORENO',        'LESTER',     'LONGALONG',   null,  '1796',    '1995-05-11', 'S', '2020-12-15', '34-4679616-6',  '1211-4887-2499',          '478-273-115', '0302-5710-2862', 'ROLLING MILL CREW',  'No. 718 Quirino Highway, San Bartolome, Novaliches, Q.C. 1116'],
            ['MORENO',        'NOEL',       'EVANGELISTA', null,  '100754',  '1965-01-07', 'M', '1995-02-08', '03-8432505-8',  '1210-7453-2754',          '177-856-280', '1905-0342-5924', 'ROLLING MILL CREW',  'No. 718 Quirino Highway, San Bartolome, Novaliches, Q.C. 1116'],
            ['MOSQUERA',      'RAMON',      'SENORIN',     null,  '100761',  '1954-11-16', 'M', '1995-02-13', '04-0139328-0',  '1060-0002-3679',          '177-856-302', '1905-0342-5967', 'SUPERVISOR',         'Blk. 17 Lot 36, Goodwill Homes I, Bagbag, Novaliches, Q.C. 1116'],
            ['MOSQUERRA',     'JERRY',      'PERLAS',      null,  '1763',    '1993-09-12', 'M', '2015-07-06', '02-3193515-1',  '1210-9969-5690',          '316-673-381', '0102-5528-5764', 'ROLLING MILL CREW',  'No. 128 Champaca St., Western Bicutan, Taguig. 1630'],
            ['NACARIO JR.',   'DIONISIO',   'TORMES',      'Jr.', '100118',  '1965-07-27', 'M', '1987-01-12', '05-0317578-8',  '1060-0002-3680',          '100-858-386', '1905-0941-8822', 'ROLLING MILL CREW',  '169 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['OBIAS',         'EDGAR',      'RODRIGUEZ',   null,  '100478',  '1958-06-13', 'M', '1993-10-26', '03-3646894-7',  '1060-0002-3734',          '100-218-231', '1905-0342-6157', 'ELECTRICIAN',        'Blk. 19 Lot 11, Cristina Homes Subd., Caloocan City. 1422'],
            ['OBLENA',        'DANNY',      'ZURBANO',     null,  '1605',    '1985-09-27', 'S', '2017-07-11', '04-2007425-0',  '1211-1005-1575',          '446-900-442', '0805-1068-7691', 'ROLLING MILL CREW',  'Blk. 27 Lot 30, Phase II NDLC II, Novaliches, Caloocan City. 1400'],
            ['OMPOC',         'CRISTOPHER', 'OCMER',       null,  '1557',    '1978-05-13', 'M', '2014-04-23', '33-5792406-9',  '1060-0001-6205',          '208-329-096', '1909-0255-6307', 'ELECTRICIAN',        'Polsan 2 Saranay, Bagumbong, Caloocan City. 1421'],
            ['PANES JR.',     'JOEMARIE',   'BANGUIS',     'Jr.', '1985',    '1994-01-04', 'M', '2017-01-24', '34-4246734-7',  '1211-1922-9095',          '452-988-448', '0202-6233-9786', 'ROLLING MILL CREW',  'Blk.12 Lot 3, Phase I, GML Village, Lingunan, Valenzuela City. 1446'],
            ['POLAR',         'ALLAN',      'PLARISAN',    null,  '100664',  '1966-03-12', 'M', '1994-08-09', '03-7848975-1',  '1060-0002-3834',          '171-419-509', '1905-0342-6815', 'ROLLING MILL CREW',  'No. 26-A, Virgo St., ACF Homes, San Bartolome, Novaliches, Q.C. 1116'],
            ['QUIDES',        'RICHARD',    'CABAIS',      null,  '980006',  '1972-12-17', 'M', '1998-07-20', '33-0960407-8',  '1210-1415-9277',          '161-197-892', '1905-0342-6920', 'ROLLING MILL CREW',  'No. 17, Urbano St., Bagbag, Novaliches, Q.C. 1116'],
            ['REBUCAN JR.',   'RODEO',      'GEBAGA',      'Jr.', '1531',    '1987-11-02', 'M', '2014-01-30', '33-9598158-5',  '1211-0996-8938',          '453-065-767', '0302-5519-7858', 'COMPANY DRIVER',     'No. 80 Sta. Veronica St., Gulod, Novaliches, Q.C.'],
            ['RECUERDO',      'ROBERTO',    'NG',          null,  '100290',  '1967-08-28', 'M', '1990-01-24', '03-8735684-6',  '1060-0002-3900',          '100-856-176', '1905-0342-7064', 'ROLLING MILL CREW',  'No. 90 Narra St., Green Acres Subdivision, San Bartolome, Novaliches, Q.C. 1116'],
            ['REPASO',        'MERWIN',     'CUNANAN',     null,  '1857',    '1991-03-09', 'M', '2016-01-14', '34-2004843-2',  '1211-5068-0943',          '481-905-083', '0202-6549-2941', 'ROLLING MILL CREW',  'Phase 1, Blk. 1 Lot 13, Celina Homes I, Camarin, Caloocan City. 1422'],
            ['RICAFORT JR.',  'ARMANDO',    'JIMENEZ',     'Jr.', '100596',  '1972-12-14', 'M', '1994-05-17', '33-3852478-9',  '1210-5849-3293',          '168-776-949', '1905-0941-9977', 'ROLLING MILL CREW',  'No. 26 Carreon Village, Novaliches, Q.C. 1125'],
            ['RIOS',          'CHARLIE',    'OPITAN',      null,  '20136',   '1984-01-29', 'M', '2015-02-18', '33-8516967-6',  '1090-0273-2564',          '238-611-073', '0105-0007-8001', 'ROLLING MILL CREW',  'Ramos Compound, Holy Cross Road, San Bartolome, Novaliches, Q.C. 1116'],
            ['RIVERA',        'ROMEO',      'REMO',        null,  '100835',  '1965-04-02', 'M', '1995-09-23', '03-8206711-4',  '1060-0002-3912',          '139-317-375', '1905-0342-7196', 'ROLLING MILL CREW',  'No. 701, San Bartolome, Novaliches, Q.C. 1116'],
            ['ROSALES',       'MARLON',     'ALCOS',       null,  '1954',    '1979-06-03', 'M', '2016-09-06', '33-8951115-2',  '1211-7061-8001',          '490-912-074', '0302-5960-3893', 'ROLLING MILL CREW',  'Lot 581 Jimilina, Bagumbong, Caloocan City. 1421'],
            ['RUBA',          'MARK JAYREL','ESPINOSA',    null,  '3044',    '1997-12-21', 'M', '2020-08-03', '34-5959882-7',  '1211-7483-4783',          '361-195-934', '0302-5984-5447', 'ROLLING MILL CREW',  'Lot 1 Blk 22, Swimming St., Maligaya Park, Caloocan City. 1422'],
            ['SABALANDE',     'KRIS',       'LAMPIOS',     null,  '201207',  '1994-10-26', 'S', '2012-11-09', '34-3524892-6',  '1210-7148-8948',          '429-022-679', '0305-1019-7832', 'ROLLING MILL CREW',  'No.148 Junji St., Rolling Hills, Kaligayahan, Novaliches, Q.C. 1126'],
            ['SAN JUAN',      'JORDAN',     'OBISO',       null,  '1594',    '1985-08-01', 'M', '2016-08-20', '33-8758413-6',  '1211-0284-3595',          '264-246-712', '0102-5219-8275', 'ROLLING MILL CREW',  'No. 041 Pasacola Dulo, Brgy., Nagkaisang Nayon, Novaliches, Q.C. 1125'],
            ['SAMONTE',       'JOELITO',    'HERBES',      null,  '960027',  '1967-07-22', 'M', '1998-07-24', '33-4100523-7',  '1060-0002-6920',          '186-122-940', '1905-0342-7471', 'ROLLING MILL CREW',  '#4 Ramos Comp., San Bartolome, Novaliches, Q.C. 1116'],
            ['SIALONGO',      'BERNALDO',   'ANGELES',     null,  '100356',  '1972-11-16', 'M', '1991-06-26', '33-1366049-3',  '1060-0002-4032',          '100-858-897', '1905-0342-7765', 'ROLLING MILL CREW',  'Phase 7-B, pkg. 4, Blk. 25, Lot 14, Bagong Silang, Caloocan City. 1428'],
            ['SOLIS',         'CESAR',      'ACALA',       null,  '1673',    '1971-02-10', 'M', '2015-02-16', '33-3581937-4',  '1211-7387-6813',          '478-206-237', '1302-5021-3310', 'ROLLING MILL CREW',  'No. 821 San Juan St., San Juan, Cainta, Rizal. 1900'],
            ['SOLIS',         'MARK DAVE',  'OLIVERON',    null,  '2057',    '1997-09-03', 'S', '2018-03-08', '34-6868321-5',  '1212-0237-4230',          '344-529-590', '0302-6150-5947', 'ROLLING MILL CREW',  'No. 003 Pugong Ginto St., Aguardiente Brgy., Sta. Monica, Novaliches, Q.C. 1117'],
            ['TUGANO',        'PAUL WALDIE','LOPEZ',       null,  '1950',    '1986-02-12', 'S', '2019-08-23', '33-9783127-7',  '1060-0148-4822',          '331-012-996', '0305-0472-7293', 'ROLLING MILL CREW',  'Blk. 11, Lot 5, Macawili Homes, Llano, Caloocan City. 1422'],
            ['TACORDA',       'ROY',        'GUARNES',     null,  '960016',  '1973-04-12', 'M', '1996-08-07', '33-1564594-0',  '1060-0002-4109',          '186-122-991', '1905-0942-0797', 'ROLLING MILL CREW',  'Phase 2, Sec. 17, Blk. 4, Lot 15, Pabahay 2000, Muzon, San Jose del Monte, Bulacan. 3023'],
            ['TINOSA',        'ERNIE',      'GAMALE',      null,  '100452',  '1974-03-21', 'M', '1993-08-12', '33-1541888-9',  '1060-0002-4209',          '160-996-738', '1905-0942-1025', 'ROLLING MILL CREW',  'No. 119 P. dela Cruz St. San Bartolome, Novaliches, Q.C. 1116'],
            ['TOMAS JR.',     'VICTORIO',   'RIVERA',      'Jr.', '1541',    '1980-09-04', 'S', '2015-08-04', '33-4798383-2',  '1060-0156-4396',          '916-722-986', '1905-1391-7124', 'ROLLING MILL CREW',  'No. 19 Phase 1 Dormitory, Pasacola, Novaliches, Q.C. 1125'],
            ['TRINIDAD',      'ERWIN',      'DIONISIO',    null,  '100358',  '1968-09-15', 'M', '1991-08-05', '33-0253825-1',  '1210-4535-3093',          '100-859-081', '1905-0342-8257', 'ROLLING MILL CREW',  'No. 136 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['UMALI',         'ANDREW',     'CORTEZANO',   null,  '1706',    '1969-12-31', 'M', '2015-02-21', '33-0037773-1',  '1060-0000-2864',          '157-716-449', '1905-0031-2029', 'QUALITY CLERK',      'No. 36 F. Bautista St., Marulas, Valenzuela City. 1440'],
            ['URSUA',         'ARNOLD',     'PARAGAS',     null,  '1365',    '1976-11-09', 'M', '2015-01-22', '33-2075111-7',  '1210-1379-4465',          '172-192-222', '1905-0342-8397', 'ROLLING MILL CREW',  'No. 1 FB Cristina Homes, Caloocan City. 1400'],
            ['VALDERAMA',     'JOSE ROMMEL','CASTELO',     null,  '100536',  '1971-09-01', 'M', '1994-02-26', '33-0416459-7',  '1060-0002-4289',          '146-927-716', '1905-0342-8419', 'ROLLING MILL CREW',  'No. 718 Quirino Highway, San Bartolome, Novaliches, Q.C. 1116'],
            ['VELASCO',       'MORGAN',     'BUGTONG',     null,  '1995',    '1980-01-23', 'S', '2017-02-01', '33-8104836-4',  '1211-8940-9528',          '428-358-225', '2300-4648-2803', 'ROLLING MILL CREW',  'No. 17 Marcos St., Dona Faustina Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['VELITARIO',     'JOSEPH',     'LAPITAN',     null,  '960082',  '1970-03-13', 'M', '1998-07-21', '33-2995659-9',  '1060-0002-4290',          '187-737-252', '1905-0342-8567', 'ROLLING MILL CREW',  'No. 252 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['VELITARIO',     'NOEL',       'LAPITAN',     null,  '100390',  '1972-09-01', 'M', '1992-07-23', '33-1601075-2',  '1060-0002-4309',          '148-705-646', '1905-0342-8575', 'ROLLING MILL CREW',  'No.154 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
            ['VELITARIO JR.', 'TITO',       'LAPITAN',     'Jr.', '100321',  '1966-12-31', 'M', '1990-04-27', '33-0743626-4',  '1060-0002-4311',          '100-859-152', '1905-0342-8591', 'ROLLING MILL CREW',  'Blk. 45, Lot 15, Phase 4, Dela Costa Homes, Gaya-gaya, San Jose del Monte, Bulacan. 3023'],
            ['VILLAFUERTE',   'DARYL',      'TELEBRICO',   null,  '1765',    '1984-03-08', 'S', '2018-10-23', '33-8932573-3',  '1211-0069-3782',          '431-677-567', '0105-0729-5296', 'ROLLING MILL CREW',  'Rivera Ext., Rockville Subd., San Bartolome, Novaliches, Q.C. 1116'],
            ['VILLAMAR',      'ALVIN',      'INSIGNE',     null,  '1785',    '1982-01-06', 'S', '2020-08-26', '33-9452245-3',  '1210-7490-6602',          '238-784-936', '0302-5798-3869', 'ROLLING MILL CREW',  'No. 028 Dormitory, Sitio Pasacola, Novaliches, Q.C. 1117'],
            ['VILLARAZA',     'MARIO',      'DICOLAYAN',   null,  '2012',    '1984-08-14', 'S', '2018-06-20', '33-0516613-5',  '1211-9334-0046',          '336-144-136', '0302-6098-0684', 'ROLLING MILL CREW',  'Area 5-B, Brgy., Sauyo, Novaliches, Q.C.'],
            ['WENCESLAO',     'DENNIS',     'CUESTA',      null,  '1268',    '1986-01-06', 'S', '2016-01-18', '33-9599333-7',  '1211-0987-4123',          '301-276-849', '0305-0585-4736', 'ROLLING MILL CREW',  'Rainbow Homes I, San Bartolome, Novaliches, Q.C. 1116'],
            ['YBAÑEZ',        'FREDDIE',    'BARCO',       null,  '100422',  '1965-05-10', 'M', '1993-01-30', '03-9562156-3',  '1060-0002-4367',          '100-430-524', '1905-0342-8826', 'ROLLING MILL CREW',  'Blk. 28, Lot 29, North Fairview Subd., Muzon, San Jose del Monte, Bulacan. 3023'],
            ['YU',            'ERWIN',      'MATILDO',     null,  '2027',    '1973-03-21', 'M', '2017-06-10', '33-1713928-9',  '1060-0001-2309',          '194-838-227', '1905-0961-3854', 'ROLLING MILL CREW',  'No. 210 P. dela Cruz St., San Bartolome, Novaliches, Q.C. 1116'],
        ];

        // ── Seed-account employees (HR Manager & Superadmin) ─────────────
        // These two have User accounts already; give them proper profiles/employee records
        $seedAccounts = [
            [
                'profile' => [
                    'first_name'  => 'Mitch',
                    'middle_name' => 'Santos',
                    'last_name'   => 'Magno',
                    'suffix'      => null,
                    'date_of_birth' => '1985-06-15',
                    'gender'      => 'female',
                    'civil_status' => 'single',
                    'mobile'      => '+63 917 000 0001',
                    'email'       => 'hrmanager@cameco.com',
                    'current_address'   => 'HR Department, Cathay Metal Corporation',
                    'permanent_address' => 'HR Department, Cathay Metal Corporation',
                    'emergency_contact_name'         => 'HR Emergency Contact',
                    'emergency_contact_relationship' => 'Colleague',
                    'emergency_contact_phone'        => '+63 917 000 0099',
                    'sss_number'        => '33-0000001-1',
                    'tin_number'        => '000-000-001-000',
                    'philhealth_number' => '00-000000001-1',
                    'pagibig_number'    => '0000-0000-0001',
                ],
                'employee' => [
                    'employee_number' => 'EMP-HR-0001',
                    'department_id'   => $hr?->id,
                    'position_id'     => $hrManager?->id,
                    'employment_type' => 'regular',
                    'date_hired'      => '2015-01-01',
                    'regularization_date' => '2015-07-01',
                    'status'          => 'active',
                ],
            ],
            [
                'profile' => [
                    'first_name'  => 'Alex',
                    'middle_name' => 'Reyes',
                    'last_name'   => 'Tamayo',
                    'suffix'      => null,
                    'date_of_birth' => '1980-01-01',
                    'gender'      => 'male',
                    'civil_status' => 'married',
                    'mobile'      => '+63 917 000 0000',
                    'email'       => 'superadmin@cameco.com',
                    'current_address'   => 'Management Office, Cathay Metal Corporation',
                    'permanent_address' => 'Management Office, Cathay Metal Corporation',
                    'emergency_contact_name'         => 'Admin Emergency Contact',
                    'emergency_contact_relationship' => 'Colleague',
                    'emergency_contact_phone'        => '+63 917 000 0099',
                    'sss_number'        => '33-0000000-0',
                    'tin_number'        => '000-000-000-000',
                    'philhealth_number' => '00-000000000-0',
                    'pagibig_number'    => '0000-0000-0000',
                ],
                'employee' => [
                    'employee_number' => 'EMP-SA-0001',
                    'department_id'   => $hr?->id,
                    'position_id'     => $prodManager?->id,
                    'employment_type' => 'regular',
                    'date_hired'      => '2010-01-01',
                    'regularization_date' => '2010-07-01',
                    'status'          => 'active',
                ],
            ],
        ];

        // ── Insert seed-account employees ─────────────────────────────────
        foreach ($seedAccounts as $data) {
            if (Employee::where('employee_number', $data['employee']['employee_number'])->exists()) {
                continue;
            }
            if (Profile::where('email', $data['profile']['email'])->exists()) {
                continue;
            }
            $profile  = Profile::create($data['profile']);
            $empData  = array_merge(
                $data['employee'],
                ['profile_id' => $profile->id, 'created_by' => $createdBy, 'updated_by' => $createdBy]
            );
            Employee::create($empData);
        }

        // ── Insert master-list employees ──────────────────────────────────
        $civilMap = ['M' => 'married', 'S' => 'single'];

        foreach ($masterList as $row) {
            [$lastName, $firstName, $middleName, $suffix, $empNo,
             $dob, $civil, $dateHired,
             $sss, $pagibig, $tin, $philhealth,
             $posStr, $address] = $row;

            $empNumber = 'CMC-' . $empNo;

            if (Employee::where('employee_number', $empNumber)->exists()) {
                continue;
            }

            // Avoid inserting duplicate profiles (same name + DOB)
            $existingProfile = Profile::where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->where('date_of_birth', $dob)
                ->first();

            if ($existingProfile) {
                continue;
            }

            $position = $resolvePosition($posStr);

            $profile = Profile::create([
                'first_name'      => $firstName,
                'middle_name'     => $middleName ?: null,
                'last_name'       => $lastName,
                'suffix'          => $suffix,
                'date_of_birth'   => $dob,
                'gender'          => 'male',   // All master-list entries are male
                'civil_status'    => $civilMap[$civil] ?? 'single',
                'current_address' => $address,
                'permanent_address' => $address,
                'sss_number'        => $sss,
                'tin_number'        => $tin,
                'philhealth_number' => $philhealth,
                'pagibig_number'    => $pagibig,
            ]);

            Employee::create([
                'employee_number' => $empNumber,
                'profile_id'      => $profile->id,
                'department_id'   => $production?->id,
                'position_id'     => $position?->id,
                'employment_type' => 'regular',
                'date_hired'      => $dateHired,
                'status'          => 'active',
                'created_by'      => $createdBy,
                'updated_by'      => $createdBy,
            ]);
        }

        // ── Photo assignment ──────────────────────────────────────────────
        $sourceDir = base_path('employee-photos-mock');
        if (is_dir($sourceDir)) {
            $allEmployees = Employee::with('profile')->orderBy('id')->get();
            $photoFiles   = collect(glob($sourceDir . '/*.jpg'))
                ->map(fn($f) => basename($f))
                ->toArray();

            $this->command->info('Assigning photos to male employees...');
            $i = 0;
            foreach ($allEmployees as $employee) {
                $profile = $employee->profile;
                if (!$profile || strtolower($profile->gender) !== 'male') {
                    continue;
                }
                $photoFile = $photoFiles[$i % count($photoFiles)] ?? null;
                if ($photoFile) {
                    $sourcePath = $sourceDir . '/' . $photoFile;
                    $destPath   = "employees/{$employee->employee_number}/profile.jpg";
                    if (file_exists($sourcePath)) {
                        \Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
                        $profile->update(['profile_picture_path' => $destPath]);
                    }
                }
                $i++;
            }
            $this->command->info('Photo assignment complete.');
        }

        $this->command->info('✅ EmployeeSeeder complete — master list + seed accounts inserted.');
    }
}