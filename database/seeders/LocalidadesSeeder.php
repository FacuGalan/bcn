<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para las localidades de Argentina
 *
 * Crea las localidades del padrón AFIP.
 * Estos datos son compartidos por todos los comercios.
 */
class LocalidadesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener IDs de provincias
        $provincias = DB::connection('config')
            ->table('provincias')
            ->pluck('id', 'codigo')
            ->toArray();

        $localidades = $this->getLocalidades($provincias);

        // Insertar en lotes de 500 para mejor performance
        $chunks = array_chunk($localidades, 500);
        $total = 0;

        foreach ($chunks as $chunk) {
            DB::connection('config')->table('localidades')->insert($chunk);
            $total += count($chunk);
        }

        $this->command->info('Localidades creadas: ' . $total);
    }

    /**
     * Obtiene el listado de localidades con sus provincias
     */
    private function getLocalidades(array $provincias): array
    {
        $now = now();
        $localidades = [];

        // CIUDAD AUTÓNOMA DE BUENOS AIRES (AR-C)
        $caba = $provincias['AR-C'];
        $localidadesCaba = [
            ['codigo_postal' => '1001', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1002', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1003', 'nombre' => 'Retiro'],
            ['codigo_postal' => '1004', 'nombre' => 'San Telmo'],
            ['codigo_postal' => '1005', 'nombre' => 'Barracas'],
            ['codigo_postal' => '1006', 'nombre' => 'Monserrat'],
            ['codigo_postal' => '1007', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1008', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1009', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1010', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1011', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1012', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1013', 'nombre' => 'Constitución'],
            ['codigo_postal' => '1014', 'nombre' => 'Monserrat'],
            ['codigo_postal' => '1015', 'nombre' => 'Retiro'],
            ['codigo_postal' => '1016', 'nombre' => 'Recoleta'],
            ['codigo_postal' => '1017', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '1018', 'nombre' => 'Monserrat'],
            ['codigo_postal' => '1019', 'nombre' => 'Monserrat'],
            ['codigo_postal' => '1020', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1021', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1022', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1023', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1024', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1025', 'nombre' => 'San Cristóbal'],
            ['codigo_postal' => '1026', 'nombre' => 'San Cristóbal'],
            ['codigo_postal' => '1027', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1028', 'nombre' => 'Balvanera'],
            ['codigo_postal' => '1029', 'nombre' => 'Constitución'],
            ['codigo_postal' => '1030', 'nombre' => 'Recoleta'],
            ['codigo_postal' => '1031', 'nombre' => 'Recoleta'],
            ['codigo_postal' => '1032', 'nombre' => 'Recoleta'],
            ['codigo_postal' => '1033', 'nombre' => 'Almagro'],
            ['codigo_postal' => '1034', 'nombre' => 'Boedo'],
            ['codigo_postal' => '1035', 'nombre' => 'Parque Patricios'],
            ['codigo_postal' => '1036', 'nombre' => 'Barracas'],
            ['codigo_postal' => '1037', 'nombre' => 'Nueva Pompeya'],
            ['codigo_postal' => '1038', 'nombre' => 'Parque Chacabuco'],
            ['codigo_postal' => '1039', 'nombre' => 'Caballito'],
            ['codigo_postal' => '1040', 'nombre' => 'Almagro'],
            ['codigo_postal' => '1041', 'nombre' => 'Almagro'],
            ['codigo_postal' => '1042', 'nombre' => 'Caballito'],
            ['codigo_postal' => '1043', 'nombre' => 'Flores'],
            ['codigo_postal' => '1044', 'nombre' => 'Flores'],
            ['codigo_postal' => '1045', 'nombre' => 'Floresta'],
            ['codigo_postal' => '1046', 'nombre' => 'Vélez Sársfield'],
            ['codigo_postal' => '1047', 'nombre' => 'Villa Luro'],
            ['codigo_postal' => '1048', 'nombre' => 'Liniers'],
            ['codigo_postal' => '1049', 'nombre' => 'Mataderos'],
            ['codigo_postal' => '1052', 'nombre' => 'Palermo'],
            ['codigo_postal' => '1053', 'nombre' => 'Palermo'],
            ['codigo_postal' => '1054', 'nombre' => 'Palermo'],
            ['codigo_postal' => '1055', 'nombre' => 'Palermo'],
            ['codigo_postal' => '1056', 'nombre' => 'Palermo'],
            ['codigo_postal' => '1057', 'nombre' => 'Villa Crespo'],
            ['codigo_postal' => '1058', 'nombre' => 'Villa Crespo'],
            ['codigo_postal' => '1059', 'nombre' => 'Chacarita'],
            ['codigo_postal' => '1060', 'nombre' => 'La Paternal'],
            ['codigo_postal' => '1061', 'nombre' => 'Villa General Mitre'],
            ['codigo_postal' => '1062', 'nombre' => 'Villa del Parque'],
            ['codigo_postal' => '1063', 'nombre' => 'Agronomía'],
            ['codigo_postal' => '1064', 'nombre' => 'Villa Pueyrredón'],
            ['codigo_postal' => '1065', 'nombre' => 'Villa Urquiza'],
            ['codigo_postal' => '1066', 'nombre' => 'Villa Ortúzar'],
            ['codigo_postal' => '1067', 'nombre' => 'Coghlan'],
            ['codigo_postal' => '1068', 'nombre' => 'Saavedra'],
            ['codigo_postal' => '1069', 'nombre' => 'Núñez'],
            ['codigo_postal' => '1070', 'nombre' => 'Belgrano'],
            ['codigo_postal' => '1071', 'nombre' => 'Belgrano'],
            ['codigo_postal' => '1072', 'nombre' => 'Colegiales'],
            ['codigo_postal' => '1073', 'nombre' => 'Belgrano'],
            ['codigo_postal' => '1074', 'nombre' => 'Belgrano'],
            ['codigo_postal' => '1082', 'nombre' => 'Puerto Madero'],
            ['codigo_postal' => '1083', 'nombre' => 'La Boca'],
            ['codigo_postal' => '1084', 'nombre' => 'La Boca'],
            ['codigo_postal' => '1085', 'nombre' => 'Barracas'],
            ['codigo_postal' => '1086', 'nombre' => 'Barracas'],
            ['codigo_postal' => '1087', 'nombre' => 'Parque Patricios'],
            ['codigo_postal' => '1088', 'nombre' => 'Villa Soldati'],
            ['codigo_postal' => '1089', 'nombre' => 'Villa Lugano'],
            ['codigo_postal' => '1090', 'nombre' => 'Villa Riachuelo'],
            ['codigo_postal' => '1091', 'nombre' => 'Parque Avellaneda'],
            ['codigo_postal' => '1092', 'nombre' => 'Versalles'],
            ['codigo_postal' => '1093', 'nombre' => 'Monte Castro'],
            ['codigo_postal' => '1094', 'nombre' => 'Villa Real'],
            ['codigo_postal' => '1095', 'nombre' => 'Villa Devoto'],
            ['codigo_postal' => '1096', 'nombre' => 'Villa Santa Rita'],
            ['codigo_postal' => '1097', 'nombre' => 'Vélez Sársfield'],
        ];

        foreach ($localidadesCaba as $loc) {
            $localidades[] = [
                'provincia_id' => $caba,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // BUENOS AIRES (AR-B) - Principales partidos y localidades
        $bsas = $provincias['AR-B'];
        $localidadesBsAs = [
            // Zona Norte
            ['codigo_postal' => '1602', 'nombre' => 'Florida'],
            ['codigo_postal' => '1603', 'nombre' => 'Villa Martelli'],
            ['codigo_postal' => '1604', 'nombre' => 'Florida Oeste'],
            ['codigo_postal' => '1605', 'nombre' => 'Munro'],
            ['codigo_postal' => '1606', 'nombre' => 'Carapachay'],
            ['codigo_postal' => '1607', 'nombre' => 'Villa Adelina'],
            ['codigo_postal' => '1609', 'nombre' => 'Boulogne'],
            ['codigo_postal' => '1610', 'nombre' => 'San Andrés'],
            ['codigo_postal' => '1611', 'nombre' => 'Don Torcuato'],
            ['codigo_postal' => '1612', 'nombre' => 'Don Torcuato'],
            ['codigo_postal' => '1613', 'nombre' => 'Los Polvorines'],
            ['codigo_postal' => '1614', 'nombre' => 'Villa de Mayo'],
            ['codigo_postal' => '1615', 'nombre' => 'Grand Bourg'],
            ['codigo_postal' => '1617', 'nombre' => 'General Pacheco'],
            ['codigo_postal' => '1618', 'nombre' => 'El Talar'],
            ['codigo_postal' => '1619', 'nombre' => 'Garín'],
            ['codigo_postal' => '1620', 'nombre' => 'Ingeniero Maschwitz'],
            ['codigo_postal' => '1621', 'nombre' => 'Dique Luján'],
            ['codigo_postal' => '1623', 'nombre' => 'Matheu'],
            ['codigo_postal' => '1625', 'nombre' => 'Escobar'],
            ['codigo_postal' => '1626', 'nombre' => 'Belén de Escobar'],
            ['codigo_postal' => '1627', 'nombre' => 'Pilar'],
            ['codigo_postal' => '1629', 'nombre' => 'Pilar'],
            ['codigo_postal' => '1631', 'nombre' => 'Del Viso'],
            ['codigo_postal' => '1635', 'nombre' => 'Derqui'],
            ['codigo_postal' => '1636', 'nombre' => 'Campana'],
            ['codigo_postal' => '1638', 'nombre' => 'Vicente López'],
            ['codigo_postal' => '1640', 'nombre' => 'Martínez'],
            ['codigo_postal' => '1641', 'nombre' => 'Acassuso'],
            ['codigo_postal' => '1642', 'nombre' => 'San Isidro'],
            ['codigo_postal' => '1643', 'nombre' => 'Beccar'],
            ['codigo_postal' => '1644', 'nombre' => 'Victoria'],
            ['codigo_postal' => '1646', 'nombre' => 'San Fernando'],
            ['codigo_postal' => '1647', 'nombre' => 'San Fernando'],
            ['codigo_postal' => '1648', 'nombre' => 'Tigre'],
            ['codigo_postal' => '1649', 'nombre' => 'Tigre'],
            ['codigo_postal' => '1650', 'nombre' => 'San Martín'],
            ['codigo_postal' => '1651', 'nombre' => 'San Andrés'],
            ['codigo_postal' => '1653', 'nombre' => 'Villa Ballester'],
            ['codigo_postal' => '1655', 'nombre' => 'José León Suárez'],
            ['codigo_postal' => '1657', 'nombre' => 'Villa Lynch'],
            ['codigo_postal' => '1658', 'nombre' => 'San Miguel'],
            ['codigo_postal' => '1659', 'nombre' => 'Campo de Mayo'],
            ['codigo_postal' => '1661', 'nombre' => 'Bella Vista'],
            ['codigo_postal' => '1663', 'nombre' => 'San Miguel'],
            ['codigo_postal' => '1664', 'nombre' => 'Muñiz'],
            ['codigo_postal' => '1665', 'nombre' => 'José C. Paz'],
            ['codigo_postal' => '1666', 'nombre' => 'Del Viso'],
            ['codigo_postal' => '1667', 'nombre' => 'Tortuguitas'],
            ['codigo_postal' => '1669', 'nombre' => 'Moreno'],
            ['codigo_postal' => '1671', 'nombre' => 'General Rodríguez'],
            ['codigo_postal' => '1672', 'nombre' => 'General Rodríguez'],
            ['codigo_postal' => '1674', 'nombre' => 'Merlo'],
            ['codigo_postal' => '1676', 'nombre' => 'Santos Lugares'],
            ['codigo_postal' => '1678', 'nombre' => 'Caseros'],
            ['codigo_postal' => '1682', 'nombre' => 'El Palomar'],
            ['codigo_postal' => '1684', 'nombre' => 'Hurlingham'],
            ['codigo_postal' => '1685', 'nombre' => 'Hurlingham'],
            ['codigo_postal' => '1686', 'nombre' => 'Hurlingham'],
            ['codigo_postal' => '1687', 'nombre' => 'Ituzaingó'],
            ['codigo_postal' => '1688', 'nombre' => 'Villa Tesei'],
            ['codigo_postal' => '1702', 'nombre' => 'Ciudadela'],
            ['codigo_postal' => '1704', 'nombre' => 'Ramos Mejía'],
            ['codigo_postal' => '1706', 'nombre' => 'Haedo'],
            ['codigo_postal' => '1708', 'nombre' => 'Morón'],
            ['codigo_postal' => '1712', 'nombre' => 'Castelar'],
            ['codigo_postal' => '1714', 'nombre' => 'Ituzaingó'],
            ['codigo_postal' => '1716', 'nombre' => 'Libertad'],
            ['codigo_postal' => '1718', 'nombre' => 'San Antonio de Padua'],
            ['codigo_postal' => '1720', 'nombre' => 'Merlo'],
            // Zona Oeste
            ['codigo_postal' => '1722', 'nombre' => 'Merlo'],
            ['codigo_postal' => '1724', 'nombre' => 'Paso del Rey'],
            ['codigo_postal' => '1742', 'nombre' => 'Moreno'],
            ['codigo_postal' => '1744', 'nombre' => 'Moreno'],
            ['codigo_postal' => '1746', 'nombre' => 'Francisco Álvarez'],
            ['codigo_postal' => '1748', 'nombre' => 'General Rodríguez'],
            ['codigo_postal' => '1752', 'nombre' => 'Luzuriaga'],
            ['codigo_postal' => '1754', 'nombre' => 'San Justo'],
            ['codigo_postal' => '1755', 'nombre' => 'San Justo'],
            ['codigo_postal' => '1757', 'nombre' => 'San Justo'],
            ['codigo_postal' => '1758', 'nombre' => 'Villa Celina'],
            ['codigo_postal' => '1759', 'nombre' => 'González Catán'],
            ['codigo_postal' => '1760', 'nombre' => 'Isidro Casanova'],
            ['codigo_postal' => '1763', 'nombre' => 'Laferrere'],
            ['codigo_postal' => '1765', 'nombre' => 'Virrey del Pino'],
            ['codigo_postal' => '1766', 'nombre' => 'La Tablada'],
            ['codigo_postal' => '1768', 'nombre' => 'Tapiales'],
            ['codigo_postal' => '1770', 'nombre' => 'Aldo Bonzi'],
            ['codigo_postal' => '1772', 'nombre' => 'Villa Madero'],
            // Zona Sur
            ['codigo_postal' => '1773', 'nombre' => 'Villa Insuperable'],
            ['codigo_postal' => '1774', 'nombre' => 'Ciudad Evita'],
            ['codigo_postal' => '1778', 'nombre' => 'Ciudad Evita'],
            ['codigo_postal' => '1802', 'nombre' => 'Aeropuerto Ezeiza'],
            ['codigo_postal' => '1804', 'nombre' => 'Ezeiza'],
            ['codigo_postal' => '1806', 'nombre' => 'Monte Grande'],
            ['codigo_postal' => '1812', 'nombre' => 'Canning'],
            ['codigo_postal' => '1814', 'nombre' => 'Cañuelas'],
            ['codigo_postal' => '1822', 'nombre' => 'Valentín Alsina'],
            ['codigo_postal' => '1824', 'nombre' => 'Lanús Oeste'],
            ['codigo_postal' => '1826', 'nombre' => 'Remedios de Escalada'],
            ['codigo_postal' => '1828', 'nombre' => 'Banfield'],
            ['codigo_postal' => '1832', 'nombre' => 'Lomas de Zamora'],
            ['codigo_postal' => '1834', 'nombre' => 'Temperley'],
            ['codigo_postal' => '1836', 'nombre' => 'Turdera'],
            ['codigo_postal' => '1838', 'nombre' => 'Longchamps'],
            ['codigo_postal' => '1842', 'nombre' => 'Monte Grande'],
            ['codigo_postal' => '1846', 'nombre' => 'Luis Guillón'],
            ['codigo_postal' => '1848', 'nombre' => 'Claypole'],
            ['codigo_postal' => '1852', 'nombre' => 'Burzaco'],
            ['codigo_postal' => '1854', 'nombre' => 'Ministro Rivadavia'],
            ['codigo_postal' => '1856', 'nombre' => 'Glew'],
            ['codigo_postal' => '1858', 'nombre' => 'Guernica'],
            ['codigo_postal' => '1864', 'nombre' => 'Presidente Perón'],
            ['codigo_postal' => '1870', 'nombre' => 'Avellaneda'],
            ['codigo_postal' => '1871', 'nombre' => 'Dock Sud'],
            ['codigo_postal' => '1872', 'nombre' => 'Sarandí'],
            ['codigo_postal' => '1874', 'nombre' => 'Villa Domínico'],
            ['codigo_postal' => '1876', 'nombre' => 'Bernal Oeste'],
            ['codigo_postal' => '1878', 'nombre' => 'Quilmes'],
            ['codigo_postal' => '1879', 'nombre' => 'Quilmes'],
            ['codigo_postal' => '1880', 'nombre' => 'Quilmes Oeste'],
            ['codigo_postal' => '1881', 'nombre' => 'Don Bosco'],
            ['codigo_postal' => '1882', 'nombre' => 'Bernal'],
            ['codigo_postal' => '1884', 'nombre' => 'Berazategui'],
            ['codigo_postal' => '1886', 'nombre' => 'Ranelagh'],
            ['codigo_postal' => '1888', 'nombre' => 'Florencio Varela'],
            ['codigo_postal' => '1889', 'nombre' => 'Florencio Varela'],
            ['codigo_postal' => '1891', 'nombre' => 'Hudson'],
            ['codigo_postal' => '1894', 'nombre' => 'Villa Elisa'],
            ['codigo_postal' => '1896', 'nombre' => 'City Bell'],
            ['codigo_postal' => '1897', 'nombre' => 'Gonnet'],
            ['codigo_postal' => '1898', 'nombre' => 'Gonnet'],
            ['codigo_postal' => '1899', 'nombre' => 'Ringuelet'],
            ['codigo_postal' => '1900', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1901', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1902', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1903', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1904', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1905', 'nombre' => 'La Plata'],
            ['codigo_postal' => '1906', 'nombre' => 'Tolosa'],
            ['codigo_postal' => '1907', 'nombre' => 'Los Hornos'],
            ['codigo_postal' => '1923', 'nombre' => 'Berisso'],
            ['codigo_postal' => '1925', 'nombre' => 'Ensenada'],
            // Interior Buenos Aires
            ['codigo_postal' => '2700', 'nombre' => 'Pergamino'],
            ['codigo_postal' => '2720', 'nombre' => 'Colón'],
            ['codigo_postal' => '2740', 'nombre' => 'Arrecifes'],
            ['codigo_postal' => '2760', 'nombre' => 'San Pedro'],
            ['codigo_postal' => '2800', 'nombre' => 'Zárate'],
            ['codigo_postal' => '2804', 'nombre' => 'Campana'],
            ['codigo_postal' => '2900', 'nombre' => 'San Nicolás'],
            ['codigo_postal' => '2930', 'nombre' => 'Ramallo'],
            ['codigo_postal' => '6000', 'nombre' => 'Junín'],
            ['codigo_postal' => '6020', 'nombre' => 'Chacabuco'],
            ['codigo_postal' => '6034', 'nombre' => 'Chivilcoy'],
            ['codigo_postal' => '6070', 'nombre' => 'Lincoln'],
            ['codigo_postal' => '6100', 'nombre' => 'Mercedes'],
            ['codigo_postal' => '6400', 'nombre' => '9 de Julio'],
            ['codigo_postal' => '6450', 'nombre' => 'Pehuajó'],
            ['codigo_postal' => '6500', 'nombre' => 'Trenque Lauquen'],
            ['codigo_postal' => '6550', 'nombre' => 'General Villegas'],
            ['codigo_postal' => '6600', 'nombre' => 'Rivadavia'],
            ['codigo_postal' => '6620', 'nombre' => 'Carlos Casares'],
            ['codigo_postal' => '6700', 'nombre' => 'Luján'],
            ['codigo_postal' => '6740', 'nombre' => 'General Las Heras'],
            ['codigo_postal' => '7000', 'nombre' => 'Tandil'],
            ['codigo_postal' => '7100', 'nombre' => 'Dolores'],
            ['codigo_postal' => '7107', 'nombre' => 'General Lavalle'],
            ['codigo_postal' => '7130', 'nombre' => 'Chascomús'],
            ['codigo_postal' => '7165', 'nombre' => 'Pila'],
            ['codigo_postal' => '7203', 'nombre' => 'General Belgrano'],
            ['codigo_postal' => '7260', 'nombre' => 'Saladillo'],
            ['codigo_postal' => '7300', 'nombre' => 'Azul'],
            ['codigo_postal' => '7400', 'nombre' => 'Olavarría'],
            ['codigo_postal' => '7500', 'nombre' => 'Tres Arroyos'],
            ['codigo_postal' => '7540', 'nombre' => 'González Chaves'],
            ['codigo_postal' => '7600', 'nombre' => 'Mar del Plata'],
            ['codigo_postal' => '7601', 'nombre' => 'Mar del Plata'],
            ['codigo_postal' => '7602', 'nombre' => 'Mar del Plata'],
            ['codigo_postal' => '7603', 'nombre' => 'Mar del Plata'],
            ['codigo_postal' => '7604', 'nombre' => 'Mar del Plata'],
            ['codigo_postal' => '7605', 'nombre' => 'Miramar'],
            ['codigo_postal' => '7607', 'nombre' => 'Miramar'],
            ['codigo_postal' => '7609', 'nombre' => 'Balcarce'],
            ['codigo_postal' => '7620', 'nombre' => 'Necochea'],
            ['codigo_postal' => '7630', 'nombre' => 'Necochea'],
            ['codigo_postal' => '7635', 'nombre' => 'Quequén'],
            ['codigo_postal' => '7500', 'nombre' => 'Tres Arroyos'],
            ['codigo_postal' => '8000', 'nombre' => 'Bahía Blanca'],
            ['codigo_postal' => '8103', 'nombre' => 'Punta Alta'],
            ['codigo_postal' => '8109', 'nombre' => 'Coronel Rosales'],
            ['codigo_postal' => '8136', 'nombre' => 'Coronel Dorrego'],
            ['codigo_postal' => '8142', 'nombre' => 'Monte Hermoso'],
            ['codigo_postal' => '8160', 'nombre' => 'Coronel Pringles'],
            ['codigo_postal' => '8170', 'nombre' => 'Coronel Suárez'],
            ['codigo_postal' => '8180', 'nombre' => 'Pigüé'],
            ['codigo_postal' => '8200', 'nombre' => 'Carhué'],
            ['codigo_postal' => '8300', 'nombre' => 'Neuquén'],
            ['codigo_postal' => '6620', 'nombre' => 'Carlos Casares'],
        ];

        foreach ($localidadesBsAs as $loc) {
            $localidades[] = [
                'provincia_id' => $bsas,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // CATAMARCA (AR-K)
        $catamarca = $provincias['AR-K'];
        $localidadesCatamarca = [
            ['codigo_postal' => '4700', 'nombre' => 'San Fernando del Valle de Catamarca'],
            ['codigo_postal' => '4701', 'nombre' => 'Catamarca'],
            ['codigo_postal' => '4702', 'nombre' => 'Catamarca'],
            ['codigo_postal' => '4705', 'nombre' => 'Valle Viejo'],
            ['codigo_postal' => '4707', 'nombre' => 'Fray Mamerto Esquiú'],
            ['codigo_postal' => '4715', 'nombre' => 'Capayán'],
            ['codigo_postal' => '4718', 'nombre' => 'Chumbicha'],
            ['codigo_postal' => '4719', 'nombre' => 'Huillapima'],
            ['codigo_postal' => '4721', 'nombre' => 'Pomán'],
            ['codigo_postal' => '4722', 'nombre' => 'Saujil'],
            ['codigo_postal' => '4724', 'nombre' => 'Mutquín'],
            ['codigo_postal' => '4726', 'nombre' => 'Colpes'],
            ['codigo_postal' => '4728', 'nombre' => 'Andalgalá'],
            ['codigo_postal' => '4740', 'nombre' => 'Belén'],
            ['codigo_postal' => '4750', 'nombre' => 'Tinogasta'],
            ['codigo_postal' => '4751', 'nombre' => 'Fiambalá'],
            ['codigo_postal' => '4760', 'nombre' => 'Santa María'],
            ['codigo_postal' => '4770', 'nombre' => 'Recreo'],
            ['codigo_postal' => '4780', 'nombre' => 'Ancasti'],
            ['codigo_postal' => '4781', 'nombre' => 'El Alto'],
            ['codigo_postal' => '4782', 'nombre' => 'Icaño'],
            ['codigo_postal' => '4783', 'nombre' => 'La Paz'],
            ['codigo_postal' => '4784', 'nombre' => 'San Antonio de la Paz'],
            ['codigo_postal' => '4750', 'nombre' => 'Tinogasta'],
        ];

        foreach ($localidadesCatamarca as $loc) {
            $localidades[] = [
                'provincia_id' => $catamarca,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // CHACO (AR-H)
        $chaco = $provincias['AR-H'];
        $localidadesChaco = [
            ['codigo_postal' => '3500', 'nombre' => 'Resistencia'],
            ['codigo_postal' => '3501', 'nombre' => 'Resistencia'],
            ['codigo_postal' => '3502', 'nombre' => 'Resistencia'],
            ['codigo_postal' => '3503', 'nombre' => 'Resistencia'],
            ['codigo_postal' => '3504', 'nombre' => 'Resistencia'],
            ['codigo_postal' => '3505', 'nombre' => 'Fontana'],
            ['codigo_postal' => '3506', 'nombre' => 'Barranqueras'],
            ['codigo_postal' => '3507', 'nombre' => 'Puerto Vilelas'],
            ['codigo_postal' => '3509', 'nombre' => 'Margarita Belén'],
            ['codigo_postal' => '3513', 'nombre' => 'Makallé'],
            ['codigo_postal' => '3514', 'nombre' => 'General San Martín'],
            ['codigo_postal' => '3516', 'nombre' => 'Corzuela'],
            ['codigo_postal' => '3520', 'nombre' => 'Presidencia Roque Sáenz Peña'],
            ['codigo_postal' => '3530', 'nombre' => 'Charata'],
            ['codigo_postal' => '3534', 'nombre' => 'Las Breñas'],
            ['codigo_postal' => '3540', 'nombre' => 'Villa Ángela'],
            ['codigo_postal' => '3542', 'nombre' => 'San Bernardo'],
            ['codigo_postal' => '3550', 'nombre' => 'Machagai'],
            ['codigo_postal' => '3561', 'nombre' => 'Quitilipi'],
            ['codigo_postal' => '3565', 'nombre' => 'Presidencia de la Plaza'],
            ['codigo_postal' => '3700', 'nombre' => 'Juan José Castelli'],
            ['codigo_postal' => '3704', 'nombre' => 'General José de San Martín'],
            ['codigo_postal' => '3705', 'nombre' => 'Pampa del Indio'],
            ['codigo_postal' => '3706', 'nombre' => 'Miraflores'],
            ['codigo_postal' => '3708', 'nombre' => 'El Sauzalito'],
        ];

        foreach ($localidadesChaco as $loc) {
            $localidades[] = [
                'provincia_id' => $chaco,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // CHUBUT (AR-U)
        $chubut = $provincias['AR-U'];
        $localidadesChubut = [
            ['codigo_postal' => '9000', 'nombre' => 'Comodoro Rivadavia'],
            ['codigo_postal' => '9001', 'nombre' => 'Comodoro Rivadavia'],
            ['codigo_postal' => '9002', 'nombre' => 'Comodoro Rivadavia'],
            ['codigo_postal' => '9003', 'nombre' => 'Comodoro Rivadavia'],
            ['codigo_postal' => '9005', 'nombre' => 'Rada Tilly'],
            ['codigo_postal' => '9011', 'nombre' => 'Caleta Olivia'],
            ['codigo_postal' => '9100', 'nombre' => 'Trelew'],
            ['codigo_postal' => '9103', 'nombre' => 'Rawson'],
            ['codigo_postal' => '9105', 'nombre' => 'Playa Unión'],
            ['codigo_postal' => '9107', 'nombre' => 'Gaiman'],
            ['codigo_postal' => '9109', 'nombre' => 'Dolavon'],
            ['codigo_postal' => '9111', 'nombre' => '28 de Julio'],
            ['codigo_postal' => '9120', 'nombre' => 'Puerto Madryn'],
            ['codigo_postal' => '9121', 'nombre' => 'Puerto Madryn'],
            ['codigo_postal' => '9200', 'nombre' => 'Esquel'],
            ['codigo_postal' => '9201', 'nombre' => 'Esquel'],
            ['codigo_postal' => '9203', 'nombre' => 'Trevelin'],
            ['codigo_postal' => '9210', 'nombre' => 'El Maitén'],
            ['codigo_postal' => '9211', 'nombre' => 'Lago Puelo'],
            ['codigo_postal' => '9212', 'nombre' => 'El Hoyo'],
            ['codigo_postal' => '9213', 'nombre' => 'Epuyén'],
            ['codigo_postal' => '9220', 'nombre' => 'Río Mayo'],
            ['codigo_postal' => '9300', 'nombre' => 'Sarmiento'],
        ];

        foreach ($localidadesChubut as $loc) {
            $localidades[] = [
                'provincia_id' => $chubut,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // CÓRDOBA (AR-X)
        $cordoba = $provincias['AR-X'];
        $localidadesCordoba = [
            ['codigo_postal' => '5000', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5001', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5002', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5003', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5004', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5005', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5006', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5007', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5008', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5009', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5010', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5011', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5012', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5014', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5016', 'nombre' => 'Córdoba'],
            ['codigo_postal' => '5100', 'nombre' => 'Alta Gracia'],
            ['codigo_postal' => '5101', 'nombre' => 'Alta Gracia'],
            ['codigo_postal' => '5105', 'nombre' => 'Anisacate'],
            ['codigo_postal' => '5107', 'nombre' => 'Santa Rosa de Calamuchita'],
            ['codigo_postal' => '5111', 'nombre' => 'Villa General Belgrano'],
            ['codigo_postal' => '5113', 'nombre' => 'La Cumbrecita'],
            ['codigo_postal' => '5115', 'nombre' => 'Villa Yacanto'],
            ['codigo_postal' => '5117', 'nombre' => 'Río Tercero'],
            ['codigo_postal' => '5118', 'nombre' => 'Río Tercero'],
            ['codigo_postal' => '5119', 'nombre' => 'Almafuerte'],
            ['codigo_postal' => '5121', 'nombre' => 'Hernando'],
            ['codigo_postal' => '5123', 'nombre' => 'Dalmacio Vélez Sársfield'],
            ['codigo_postal' => '5125', 'nombre' => 'Tancacha'],
            ['codigo_postal' => '5127', 'nombre' => 'Bell Ville'],
            ['codigo_postal' => '5129', 'nombre' => 'General Cabrera'],
            ['codigo_postal' => '5131', 'nombre' => 'Justiniano Posse'],
            ['codigo_postal' => '5133', 'nombre' => 'General Roca'],
            ['codigo_postal' => '5145', 'nombre' => 'Embalse'],
            ['codigo_postal' => '5150', 'nombre' => 'Villa Carlos Paz'],
            ['codigo_postal' => '5152', 'nombre' => 'Villa Carlos Paz'],
            ['codigo_postal' => '5153', 'nombre' => 'Tanti'],
            ['codigo_postal' => '5155', 'nombre' => 'Bialet Massé'],
            ['codigo_postal' => '5156', 'nombre' => 'Santa María de Punilla'],
            ['codigo_postal' => '5158', 'nombre' => 'Cosquín'],
            ['codigo_postal' => '5164', 'nombre' => 'La Falda'],
            ['codigo_postal' => '5166', 'nombre' => 'Huerta Grande'],
            ['codigo_postal' => '5168', 'nombre' => 'Villa Giardino'],
            ['codigo_postal' => '5172', 'nombre' => 'La Cumbre'],
            ['codigo_postal' => '5174', 'nombre' => 'Cruz del Eje'],
            ['codigo_postal' => '5176', 'nombre' => 'Capilla del Monte'],
            ['codigo_postal' => '5178', 'nombre' => 'San Marcos Sierras'],
            ['codigo_postal' => '5184', 'nombre' => 'Deán Funes'],
            ['codigo_postal' => '5186', 'nombre' => 'Jesús María'],
            ['codigo_postal' => '5188', 'nombre' => 'Colonia Caroya'],
            ['codigo_postal' => '5189', 'nombre' => 'Sinsacate'],
            ['codigo_postal' => '5196', 'nombre' => 'Río Ceballos'],
            ['codigo_postal' => '5197', 'nombre' => 'Unquillo'],
            ['codigo_postal' => '5199', 'nombre' => 'Mendiolaza'],
            ['codigo_postal' => '5200', 'nombre' => 'Villa del Rosario'],
            ['codigo_postal' => '5220', 'nombre' => 'Villa María'],
            ['codigo_postal' => '5223', 'nombre' => 'Villa Nueva'],
            ['codigo_postal' => '5229', 'nombre' => 'Villa del Totoral'],
            ['codigo_postal' => '5231', 'nombre' => 'Salsacate'],
            ['codigo_postal' => '5280', 'nombre' => 'Marcos Juárez'],
            ['codigo_postal' => '5282', 'nombre' => 'Leones'],
            ['codigo_postal' => '5284', 'nombre' => 'Corral de Bustos'],
            ['codigo_postal' => '5300', 'nombre' => 'La Rioja'],
            ['codigo_postal' => '5400', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5500', 'nombre' => 'Mendoza'],
            ['codigo_postal' => '5600', 'nombre' => 'San Rafael'],
            ['codigo_postal' => '5700', 'nombre' => 'San Luis'],
            ['codigo_postal' => '5800', 'nombre' => 'Río Cuarto'],
            ['codigo_postal' => '5801', 'nombre' => 'Río Cuarto'],
            ['codigo_postal' => '5802', 'nombre' => 'Río Cuarto'],
            ['codigo_postal' => '5803', 'nombre' => 'Río Cuarto'],
            ['codigo_postal' => '5805', 'nombre' => 'Las Higueras'],
            ['codigo_postal' => '5807', 'nombre' => 'Las Acequias'],
            ['codigo_postal' => '5813', 'nombre' => 'Sampacho'],
            ['codigo_postal' => '5818', 'nombre' => 'Vicuña Mackenna'],
            ['codigo_postal' => '5819', 'nombre' => 'General Cabrera'],
            ['codigo_postal' => '5830', 'nombre' => 'Huinca Renancó'],
            ['codigo_postal' => '5850', 'nombre' => 'Río Tercero'],
            ['codigo_postal' => '5856', 'nombre' => 'Oliva'],
            ['codigo_postal' => '5870', 'nombre' => 'Laboulaye'],
            ['codigo_postal' => '5900', 'nombre' => 'Villa María'],
            ['codigo_postal' => '5901', 'nombre' => 'Villa Nueva'],
            ['codigo_postal' => '5903', 'nombre' => 'Ballesteros'],
            ['codigo_postal' => '5904', 'nombre' => 'General Deheza'],
        ];

        foreach ($localidadesCordoba as $loc) {
            $localidades[] = [
                'provincia_id' => $cordoba,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // CORRIENTES (AR-W)
        $corrientes = $provincias['AR-W'];
        $localidadesCorrientes = [
            ['codigo_postal' => '3400', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3401', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3402', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3403', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3404', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3405', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3406', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3407', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3408', 'nombre' => 'Corrientes'],
            ['codigo_postal' => '3412', 'nombre' => 'Paso de los Libres'],
            ['codigo_postal' => '3416', 'nombre' => 'Curuzú Cuatiá'],
            ['codigo_postal' => '3418', 'nombre' => 'Mercedes'],
            ['codigo_postal' => '3420', 'nombre' => 'Esquina'],
            ['codigo_postal' => '3423', 'nombre' => 'Bella Vista'],
            ['codigo_postal' => '3427', 'nombre' => 'Saladas'],
            ['codigo_postal' => '3432', 'nombre' => 'Ituzaingó'],
            ['codigo_postal' => '3440', 'nombre' => 'Goya'],
            ['codigo_postal' => '3445', 'nombre' => 'Lavalle'],
            ['codigo_postal' => '3448', 'nombre' => 'Santa Lucía'],
            ['codigo_postal' => '3450', 'nombre' => 'Monte Caseros'],
            ['codigo_postal' => '3454', 'nombre' => 'Mocoretá'],
            ['codigo_postal' => '3460', 'nombre' => 'Santo Tomé'],
            ['codigo_postal' => '3463', 'nombre' => 'Virasoro'],
            ['codigo_postal' => '3466', 'nombre' => 'Alvear'],
            ['codigo_postal' => '3470', 'nombre' => 'Empedrado'],
        ];

        foreach ($localidadesCorrientes as $loc) {
            $localidades[] = [
                'provincia_id' => $corrientes,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // ENTRE RÍOS (AR-E)
        $entreRios = $provincias['AR-E'];
        $localidadesEntreRios = [
            ['codigo_postal' => '3100', 'nombre' => 'Paraná'],
            ['codigo_postal' => '3101', 'nombre' => 'Paraná'],
            ['codigo_postal' => '3102', 'nombre' => 'Paraná'],
            ['codigo_postal' => '3103', 'nombre' => 'Paraná'],
            ['codigo_postal' => '3104', 'nombre' => 'Paraná'],
            ['codigo_postal' => '3105', 'nombre' => 'San Benito'],
            ['codigo_postal' => '3107', 'nombre' => 'Oro Verde'],
            ['codigo_postal' => '3108', 'nombre' => 'Colonia Avellaneda'],
            ['codigo_postal' => '3114', 'nombre' => 'Victoria'],
            ['codigo_postal' => '3116', 'nombre' => 'Diamante'],
            ['codigo_postal' => '3118', 'nombre' => 'Crespo'],
            ['codigo_postal' => '3120', 'nombre' => 'Ramírez'],
            ['codigo_postal' => '3133', 'nombre' => 'Nogoyá'],
            ['codigo_postal' => '3135', 'nombre' => 'Rosario del Tala'],
            ['codigo_postal' => '3137', 'nombre' => 'Gualeguay'],
            ['codigo_postal' => '3140', 'nombre' => 'Larroque'],
            ['codigo_postal' => '3142', 'nombre' => 'Urdinarrain'],
            ['codigo_postal' => '3150', 'nombre' => 'Villaguay'],
            ['codigo_postal' => '3152', 'nombre' => 'Villa Domínguez'],
            ['codigo_postal' => '3153', 'nombre' => 'Basavilbaso'],
            ['codigo_postal' => '3154', 'nombre' => 'Caseros'],
            ['codigo_postal' => '3155', 'nombre' => 'Maciá'],
            ['codigo_postal' => '3158', 'nombre' => 'Villa Clara'],
            ['codigo_postal' => '3164', 'nombre' => 'La Paz'],
            ['codigo_postal' => '3170', 'nombre' => 'Federal'],
            ['codigo_postal' => '3177', 'nombre' => 'Feliciano'],
            ['codigo_postal' => '3180', 'nombre' => 'Concordia'],
            ['codigo_postal' => '3181', 'nombre' => 'Concordia'],
            ['codigo_postal' => '3190', 'nombre' => 'Chajarí'],
            ['codigo_postal' => '3192', 'nombre' => 'Federación'],
            ['codigo_postal' => '3196', 'nombre' => 'Santa Ana'],
            ['codigo_postal' => '3200', 'nombre' => 'Gualeguaychú'],
            ['codigo_postal' => '3201', 'nombre' => 'Gualeguaychú'],
            ['codigo_postal' => '3220', 'nombre' => 'Colón'],
            ['codigo_postal' => '3222', 'nombre' => 'San José'],
            ['codigo_postal' => '3230', 'nombre' => 'Concepción del Uruguay'],
            ['codigo_postal' => '3231', 'nombre' => 'Concepción del Uruguay'],
            ['codigo_postal' => '3260', 'nombre' => 'Islas del Ibicuy'],
        ];

        foreach ($localidadesEntreRios as $loc) {
            $localidades[] = [
                'provincia_id' => $entreRios,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // FORMOSA (AR-P)
        $formosa = $provincias['AR-P'];
        $localidadesFormosa = [
            ['codigo_postal' => '3600', 'nombre' => 'Formosa'],
            ['codigo_postal' => '3601', 'nombre' => 'Formosa'],
            ['codigo_postal' => '3610', 'nombre' => 'Clorinda'],
            ['codigo_postal' => '3611', 'nombre' => 'Laguna Blanca'],
            ['codigo_postal' => '3612', 'nombre' => 'Riacho He-He'],
            ['codigo_postal' => '3614', 'nombre' => 'El Colorado'],
            ['codigo_postal' => '3620', 'nombre' => 'Pirané'],
            ['codigo_postal' => '3621', 'nombre' => 'Mayor Vicente Villafañe'],
            ['codigo_postal' => '3630', 'nombre' => 'Las Lomitas'],
            ['codigo_postal' => '3632', 'nombre' => 'Pozo del Tigre'],
            ['codigo_postal' => '3634', 'nombre' => 'Ibarreta'],
            ['codigo_postal' => '3636', 'nombre' => 'Comandante Fontana'],
            ['codigo_postal' => '3638', 'nombre' => 'Ingeniero Juárez'],
        ];

        foreach ($localidadesFormosa as $loc) {
            $localidades[] = [
                'provincia_id' => $formosa,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // JUJUY (AR-Y)
        $jujuy = $provincias['AR-Y'];
        $localidadesJujuy = [
            ['codigo_postal' => '4600', 'nombre' => 'San Salvador de Jujuy'],
            ['codigo_postal' => '4601', 'nombre' => 'San Salvador de Jujuy'],
            ['codigo_postal' => '4603', 'nombre' => 'Palpalá'],
            ['codigo_postal' => '4608', 'nombre' => 'Yala'],
            ['codigo_postal' => '4612', 'nombre' => 'Perico'],
            ['codigo_postal' => '4616', 'nombre' => 'San Pedro de Jujuy'],
            ['codigo_postal' => '4618', 'nombre' => 'La Esperanza'],
            ['codigo_postal' => '4622', 'nombre' => 'Libertador General San Martín'],
            ['codigo_postal' => '4624', 'nombre' => 'Fraile Pintado'],
            ['codigo_postal' => '4626', 'nombre' => 'Calilegua'],
            ['codigo_postal' => '4630', 'nombre' => 'Humahuaca'],
            ['codigo_postal' => '4631', 'nombre' => 'Tilcara'],
            ['codigo_postal' => '4633', 'nombre' => 'Purmamarca'],
            ['codigo_postal' => '4634', 'nombre' => 'Maimará'],
            ['codigo_postal' => '4640', 'nombre' => 'La Quiaca'],
            ['codigo_postal' => '4641', 'nombre' => 'Abra Pampa'],
            ['codigo_postal' => '4643', 'nombre' => 'Tres Cruces'],
            ['codigo_postal' => '4644', 'nombre' => 'Santa Catalina'],
            ['codigo_postal' => '4646', 'nombre' => 'Rinconada'],
            ['codigo_postal' => '4650', 'nombre' => 'Susques'],
            ['codigo_postal' => '4651', 'nombre' => 'Coranzulí'],
        ];

        foreach ($localidadesJujuy as $loc) {
            $localidades[] = [
                'provincia_id' => $jujuy,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // LA PAMPA (AR-L)
        $laPampa = $provincias['AR-L'];
        $localidadesLaPampa = [
            ['codigo_postal' => '6300', 'nombre' => 'Santa Rosa'],
            ['codigo_postal' => '6301', 'nombre' => 'Santa Rosa'],
            ['codigo_postal' => '6303', 'nombre' => 'Toay'],
            ['codigo_postal' => '6320', 'nombre' => 'General Pico'],
            ['codigo_postal' => '6326', 'nombre' => 'Realicó'],
            ['codigo_postal' => '6331', 'nombre' => 'Eduardo Castex'],
            ['codigo_postal' => '6334', 'nombre' => 'General Acha'],
            ['codigo_postal' => '6360', 'nombre' => 'Macachín'],
            ['codigo_postal' => '6380', 'nombre' => 'Victorica'],
            ['codigo_postal' => '6388', 'nombre' => 'Telén'],
            ['codigo_postal' => '6387', 'nombre' => 'Carro Quemado'],
            ['codigo_postal' => '6383', 'nombre' => 'Luan Toro'],
            ['codigo_postal' => '8136', 'nombre' => '25 de Mayo'],
            ['codigo_postal' => '8138', 'nombre' => 'Gobernador Duval'],
            ['codigo_postal' => '8201', 'nombre' => 'La Adela'],
        ];

        foreach ($localidadesLaPampa as $loc) {
            $localidades[] = [
                'provincia_id' => $laPampa,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // LA RIOJA (AR-F)
        $laRioja = $provincias['AR-F'];
        $localidadesLaRioja = [
            ['codigo_postal' => '5300', 'nombre' => 'La Rioja'],
            ['codigo_postal' => '5301', 'nombre' => 'La Rioja'],
            ['codigo_postal' => '5302', 'nombre' => 'La Rioja'],
            ['codigo_postal' => '5304', 'nombre' => 'Sanagasta'],
            ['codigo_postal' => '5310', 'nombre' => 'Chilecito'],
            ['codigo_postal' => '5311', 'nombre' => 'Chilecito'],
            ['codigo_postal' => '5315', 'nombre' => 'Nonogasta'],
            ['codigo_postal' => '5317', 'nombre' => 'Anguinán'],
            ['codigo_postal' => '5340', 'nombre' => 'Chamical'],
            ['codigo_postal' => '5353', 'nombre' => 'Villa Unión'],
            ['codigo_postal' => '5357', 'nombre' => 'Vinchina'],
            ['codigo_postal' => '5360', 'nombre' => 'Chepes'],
            ['codigo_postal' => '5380', 'nombre' => 'Ulapes'],
            ['codigo_postal' => '5385', 'nombre' => 'Olta'],
        ];

        foreach ($localidadesLaRioja as $loc) {
            $localidades[] = [
                'provincia_id' => $laRioja,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // MENDOZA (AR-M)
        $mendoza = $provincias['AR-M'];
        $localidadesMendoza = [
            ['codigo_postal' => '5500', 'nombre' => 'Mendoza'],
            ['codigo_postal' => '5501', 'nombre' => 'Godoy Cruz'],
            ['codigo_postal' => '5502', 'nombre' => 'Godoy Cruz'],
            ['codigo_postal' => '5503', 'nombre' => 'Carrodilla'],
            ['codigo_postal' => '5504', 'nombre' => 'Chacras de Coria'],
            ['codigo_postal' => '5505', 'nombre' => 'Luján de Cuyo'],
            ['codigo_postal' => '5506', 'nombre' => 'Vistalba'],
            ['codigo_postal' => '5507', 'nombre' => 'Las Compuertas'],
            ['codigo_postal' => '5509', 'nombre' => 'Cacheuta'],
            ['codigo_postal' => '5513', 'nombre' => 'Potrerillos'],
            ['codigo_postal' => '5514', 'nombre' => 'Vallecitos'],
            ['codigo_postal' => '5515', 'nombre' => 'Las Cuevas'],
            ['codigo_postal' => '5519', 'nombre' => 'Uspallata'],
            ['codigo_postal' => '5521', 'nombre' => 'Puente del Inca'],
            ['codigo_postal' => '5528', 'nombre' => 'Las Heras'],
            ['codigo_postal' => '5531', 'nombre' => 'Guaymallén'],
            ['codigo_postal' => '5533', 'nombre' => 'Rodeo de la Cruz'],
            ['codigo_postal' => '5535', 'nombre' => 'San Martín'],
            ['codigo_postal' => '5537', 'nombre' => 'Palmira'],
            ['codigo_postal' => '5539', 'nombre' => 'Junín'],
            ['codigo_postal' => '5541', 'nombre' => 'Rivadavia'],
            ['codigo_postal' => '5543', 'nombre' => 'Santa Rosa'],
            ['codigo_postal' => '5545', 'nombre' => 'La Paz'],
            ['codigo_postal' => '5549', 'nombre' => 'Desaguadero'],
            ['codigo_postal' => '5551', 'nombre' => 'Maipú'],
            ['codigo_postal' => '5553', 'nombre' => 'Coquimbito'],
            ['codigo_postal' => '5559', 'nombre' => 'Russell'],
            ['codigo_postal' => '5560', 'nombre' => 'Tunuyán'],
            ['codigo_postal' => '5561', 'nombre' => 'Vista Flores'],
            ['codigo_postal' => '5563', 'nombre' => 'Tupungato'],
            ['codigo_postal' => '5565', 'nombre' => 'San Carlos'],
            ['codigo_postal' => '5567', 'nombre' => 'La Consulta'],
            ['codigo_postal' => '5569', 'nombre' => 'Pareditas'],
            ['codigo_postal' => '5600', 'nombre' => 'San Rafael'],
            ['codigo_postal' => '5601', 'nombre' => 'San Rafael'],
            ['codigo_postal' => '5605', 'nombre' => 'Villa Atuel'],
            ['codigo_postal' => '5607', 'nombre' => 'Monte Comán'],
            ['codigo_postal' => '5609', 'nombre' => 'Rama Caída'],
            ['codigo_postal' => '5613', 'nombre' => 'Real del Padre'],
            ['codigo_postal' => '5620', 'nombre' => 'General Alvear'],
            ['codigo_postal' => '5621', 'nombre' => 'Bowen'],
            ['codigo_postal' => '5622', 'nombre' => 'Carmensa'],
            ['codigo_postal' => '5624', 'nombre' => 'San Pedro del Atuel'],
            ['codigo_postal' => '5630', 'nombre' => 'Malargüe'],
        ];

        foreach ($localidadesMendoza as $loc) {
            $localidades[] = [
                'provincia_id' => $mendoza,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // MISIONES (AR-N)
        $misiones = $provincias['AR-N'];
        $localidadesMisiones = [
            ['codigo_postal' => '3300', 'nombre' => 'Posadas'],
            ['codigo_postal' => '3301', 'nombre' => 'Posadas'],
            ['codigo_postal' => '3302', 'nombre' => 'Posadas'],
            ['codigo_postal' => '3303', 'nombre' => 'Posadas'],
            ['codigo_postal' => '3304', 'nombre' => 'Garupá'],
            ['codigo_postal' => '3306', 'nombre' => 'Candelaria'],
            ['codigo_postal' => '3308', 'nombre' => 'Santa Ana'],
            ['codigo_postal' => '3310', 'nombre' => 'Apóstoles'],
            ['codigo_postal' => '3313', 'nombre' => 'San José'],
            ['codigo_postal' => '3315', 'nombre' => 'Oberá'],
            ['codigo_postal' => '3317', 'nombre' => 'Campo Ramón'],
            ['codigo_postal' => '3320', 'nombre' => 'Leandro N. Alem'],
            ['codigo_postal' => '3322', 'nombre' => 'Bonpland'],
            ['codigo_postal' => '3324', 'nombre' => 'Arroyo del Medio'],
            ['codigo_postal' => '3326', 'nombre' => 'Panambí'],
            ['codigo_postal' => '3328', 'nombre' => 'Alba Posse'],
            ['codigo_postal' => '3330', 'nombre' => 'Jardín América'],
            ['codigo_postal' => '3332', 'nombre' => 'Puerto Rico'],
            ['codigo_postal' => '3334', 'nombre' => 'Capioví'],
            ['codigo_postal' => '3336', 'nombre' => 'Ruiz de Montoya'],
            ['codigo_postal' => '3340', 'nombre' => 'Eldorado'],
            ['codigo_postal' => '3350', 'nombre' => 'Montecarlo'],
            ['codigo_postal' => '3352', 'nombre' => 'Puerto Piray'],
            ['codigo_postal' => '3356', 'nombre' => 'Puerto Esperanza'],
            ['codigo_postal' => '3358', 'nombre' => 'Wanda'],
            ['codigo_postal' => '3370', 'nombre' => 'Puerto Iguazú'],
            ['codigo_postal' => '3380', 'nombre' => 'Bernardo de Irigoyen'],
            ['codigo_postal' => '3382', 'nombre' => 'San Antonio'],
            ['codigo_postal' => '3384', 'nombre' => 'San Pedro'],
        ];

        foreach ($localidadesMisiones as $loc) {
            $localidades[] = [
                'provincia_id' => $misiones,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // NEUQUÉN (AR-Q)
        $neuquen = $provincias['AR-Q'];
        $localidadesNeuquen = [
            ['codigo_postal' => '8300', 'nombre' => 'Neuquén'],
            ['codigo_postal' => '8302', 'nombre' => 'Centenario'],
            ['codigo_postal' => '8303', 'nombre' => 'Cinco Saltos'],
            ['codigo_postal' => '8305', 'nombre' => 'Plottier'],
            ['codigo_postal' => '8307', 'nombre' => 'Senillosa'],
            ['codigo_postal' => '8309', 'nombre' => 'Arroyito'],
            ['codigo_postal' => '8311', 'nombre' => 'Picún Leufú'],
            ['codigo_postal' => '8313', 'nombre' => 'Piedra del Águila'],
            ['codigo_postal' => '8315', 'nombre' => 'Aluminé'],
            ['codigo_postal' => '8317', 'nombre' => 'Villa Pehuenia'],
            ['codigo_postal' => '8320', 'nombre' => 'Cutral Co'],
            ['codigo_postal' => '8322', 'nombre' => 'Plaza Huincul'],
            ['codigo_postal' => '8324', 'nombre' => 'Añelo'],
            ['codigo_postal' => '8326', 'nombre' => 'Rincón de los Sauces'],
            ['codigo_postal' => '8328', 'nombre' => 'Chos Malal'],
            ['codigo_postal' => '8340', 'nombre' => 'Zapala'],
            ['codigo_postal' => '8342', 'nombre' => 'Mariano Moreno'],
            ['codigo_postal' => '8345', 'nombre' => 'Las Lajas'],
            ['codigo_postal' => '8347', 'nombre' => 'Loncopué'],
            ['codigo_postal' => '8349', 'nombre' => 'Covunco Abajo'],
            ['codigo_postal' => '8370', 'nombre' => 'San Martín de los Andes'],
            ['codigo_postal' => '8371', 'nombre' => 'Chapelco'],
            ['codigo_postal' => '8382', 'nombre' => 'Junín de los Andes'],
            ['codigo_postal' => '8384', 'nombre' => 'Huechulaufquén'],
            ['codigo_postal' => '8400', 'nombre' => 'Villa La Angostura'],
            ['codigo_postal' => '8401', 'nombre' => 'San Carlos de Bariloche'],
        ];

        foreach ($localidadesNeuquen as $loc) {
            $localidades[] = [
                'provincia_id' => $neuquen,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // RÍO NEGRO (AR-R)
        $rioNegro = $provincias['AR-R'];
        $localidadesRioNegro = [
            ['codigo_postal' => '8500', 'nombre' => 'Viedma'],
            ['codigo_postal' => '8501', 'nombre' => 'Viedma'],
            ['codigo_postal' => '8503', 'nombre' => 'Carmen de Patagones'],
            ['codigo_postal' => '8504', 'nombre' => 'José de San Martín'],
            ['codigo_postal' => '8505', 'nombre' => 'General Conesa'],
            ['codigo_postal' => '8506', 'nombre' => 'Guardia Mitre'],
            ['codigo_postal' => '8508', 'nombre' => 'San Antonio Oeste'],
            ['codigo_postal' => '8520', 'nombre' => 'San Carlos de Bariloche'],
            ['codigo_postal' => '8521', 'nombre' => 'San Carlos de Bariloche'],
            ['codigo_postal' => '8530', 'nombre' => 'El Bolsón'],
            ['codigo_postal' => '8532', 'nombre' => 'Lago Puelo'],
            ['codigo_postal' => '8534', 'nombre' => 'Epuyén'],
            ['codigo_postal' => '8536', 'nombre' => 'Cholila'],
            ['codigo_postal' => '8400', 'nombre' => 'Villa La Angostura'],
            ['codigo_postal' => '8332', 'nombre' => 'General Roca'],
            ['codigo_postal' => '8324', 'nombre' => 'Cipolletti'],
            ['codigo_postal' => '8326', 'nombre' => 'Fernández Oro'],
            ['codigo_postal' => '8328', 'nombre' => 'Allen'],
            ['codigo_postal' => '8334', 'nombre' => 'Regina'],
            ['codigo_postal' => '8336', 'nombre' => 'Villa Regina'],
            ['codigo_postal' => '8338', 'nombre' => 'Ingeniero Huergo'],
            ['codigo_postal' => '8340', 'nombre' => 'Chichinales'],
            ['codigo_postal' => '8360', 'nombre' => 'Choele Choel'],
            ['codigo_postal' => '8361', 'nombre' => 'Darwin'],
            ['codigo_postal' => '8363', 'nombre' => 'Lamarque'],
            ['codigo_postal' => '8364', 'nombre' => 'Luis Beltrán'],
            ['codigo_postal' => '8430', 'nombre' => 'El Cuy'],
            ['codigo_postal' => '8432', 'nombre' => 'Ingeniero Jacobacci'],
            ['codigo_postal' => '8434', 'nombre' => 'Maquinchao'],
            ['codigo_postal' => '8436', 'nombre' => 'Los Menucos'],
            ['codigo_postal' => '8438', 'nombre' => 'Sierra Colorada'],
            ['codigo_postal' => '8440', 'nombre' => 'Ministro Ramos Mexía'],
            ['codigo_postal' => '8521', 'nombre' => 'San Carlos de Bariloche'],
        ];

        foreach ($localidadesRioNegro as $loc) {
            $localidades[] = [
                'provincia_id' => $rioNegro,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SALTA (AR-A)
        $salta = $provincias['AR-A'];
        $localidadesSalta = [
            ['codigo_postal' => '4400', 'nombre' => 'Salta'],
            ['codigo_postal' => '4401', 'nombre' => 'Salta'],
            ['codigo_postal' => '4402', 'nombre' => 'Salta'],
            ['codigo_postal' => '4403', 'nombre' => 'Salta'],
            ['codigo_postal' => '4404', 'nombre' => 'Salta'],
            ['codigo_postal' => '4405', 'nombre' => 'San Lorenzo'],
            ['codigo_postal' => '4406', 'nombre' => 'Vaqueros'],
            ['codigo_postal' => '4407', 'nombre' => 'La Caldera'],
            ['codigo_postal' => '4409', 'nombre' => 'Cerrillos'],
            ['codigo_postal' => '4411', 'nombre' => 'Campo Quijano'],
            ['codigo_postal' => '4413', 'nombre' => 'Rosario de Lerma'],
            ['codigo_postal' => '4419', 'nombre' => 'San Antonio de los Cobres'],
            ['codigo_postal' => '4421', 'nombre' => 'Santa Rosa de Tastil'],
            ['codigo_postal' => '4425', 'nombre' => 'La Poma'],
            ['codigo_postal' => '4427', 'nombre' => 'Cachi'],
            ['codigo_postal' => '4430', 'nombre' => 'Cafayate'],
            ['codigo_postal' => '4431', 'nombre' => 'Animaná'],
            ['codigo_postal' => '4433', 'nombre' => 'San Carlos'],
            ['codigo_postal' => '4434', 'nombre' => 'Molinos'],
            ['codigo_postal' => '4440', 'nombre' => 'Metán'],
            ['codigo_postal' => '4444', 'nombre' => 'El Galpón'],
            ['codigo_postal' => '4448', 'nombre' => 'Rosario de la Frontera'],
            ['codigo_postal' => '4450', 'nombre' => 'General Güemes'],
            ['codigo_postal' => '4452', 'nombre' => 'Campo Santo'],
            ['codigo_postal' => '4454', 'nombre' => 'El Bordo'],
            ['codigo_postal' => '4456', 'nombre' => 'Cobos'],
            ['codigo_postal' => '4458', 'nombre' => 'Cabeza de Buey'],
            ['codigo_postal' => '4460', 'nombre' => 'Orán'],
            ['codigo_postal' => '4466', 'nombre' => 'Hipólito Yrigoyen'],
            ['codigo_postal' => '4468', 'nombre' => 'Pichanal'],
            ['codigo_postal' => '4470', 'nombre' => 'Profesor Salvador Mazza'],
            ['codigo_postal' => '4530', 'nombre' => 'Tartagal'],
            ['codigo_postal' => '4534', 'nombre' => 'Embarcación'],
            ['codigo_postal' => '4540', 'nombre' => 'Mosconi'],
            ['codigo_postal' => '4544', 'nombre' => 'Aguaray'],
            ['codigo_postal' => '4546', 'nombre' => 'Dragones'],
            ['codigo_postal' => '4548', 'nombre' => 'Santa Victoria Este'],
            ['codigo_postal' => '4550', 'nombre' => 'Rivadavia'],
        ];

        foreach ($localidadesSalta as $loc) {
            $localidades[] = [
                'provincia_id' => $salta,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SAN JUAN (AR-J)
        $sanJuan = $provincias['AR-J'];
        $localidadesSanJuan = [
            ['codigo_postal' => '5400', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5401', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5402', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5403', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5404', 'nombre' => 'San Juan'],
            ['codigo_postal' => '5405', 'nombre' => 'Rivadavia'],
            ['codigo_postal' => '5407', 'nombre' => 'Santa Lucía'],
            ['codigo_postal' => '5409', 'nombre' => 'Chimbas'],
            ['codigo_postal' => '5411', 'nombre' => 'Capital'],
            ['codigo_postal' => '5413', 'nombre' => 'Rawson'],
            ['codigo_postal' => '5415', 'nombre' => 'Pocito'],
            ['codigo_postal' => '5421', 'nombre' => 'Albardón'],
            ['codigo_postal' => '5423', 'nombre' => 'San Martín'],
            ['codigo_postal' => '5427', 'nombre' => 'Angaco'],
            ['codigo_postal' => '5431', 'nombre' => 'Caucete'],
            ['codigo_postal' => '5435', 'nombre' => '25 de Mayo'],
            ['codigo_postal' => '5437', 'nombre' => '9 de Julio'],
            ['codigo_postal' => '5443', 'nombre' => 'Sarmiento'],
            ['codigo_postal' => '5447', 'nombre' => 'Jáchal'],
            ['codigo_postal' => '5460', 'nombre' => 'Iglesia'],
            ['codigo_postal' => '5462', 'nombre' => 'Rodeo'],
            ['codigo_postal' => '5467', 'nombre' => 'Barreal'],
            ['codigo_postal' => '5470', 'nombre' => 'Calingasta'],
            ['codigo_postal' => '5449', 'nombre' => 'Valle Fértil'],
            ['codigo_postal' => '5453', 'nombre' => 'Ullum'],
            ['codigo_postal' => '5455', 'nombre' => 'Zonda'],
        ];

        foreach ($localidadesSanJuan as $loc) {
            $localidades[] = [
                'provincia_id' => $sanJuan,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SAN LUIS (AR-D)
        $sanLuis = $provincias['AR-D'];
        $localidadesSanLuis = [
            ['codigo_postal' => '5700', 'nombre' => 'San Luis'],
            ['codigo_postal' => '5701', 'nombre' => 'San Luis'],
            ['codigo_postal' => '5702', 'nombre' => 'San Luis'],
            ['codigo_postal' => '5703', 'nombre' => 'San Luis'],
            ['codigo_postal' => '5710', 'nombre' => 'Juana Koslay'],
            ['codigo_postal' => '5711', 'nombre' => 'La Punta'],
            ['codigo_postal' => '5713', 'nombre' => 'El Volcán'],
            ['codigo_postal' => '5719', 'nombre' => 'Potrero de los Funes'],
            ['codigo_postal' => '5721', 'nombre' => 'El Trapiche'],
            ['codigo_postal' => '5723', 'nombre' => 'La Carolina'],
            ['codigo_postal' => '5730', 'nombre' => 'Villa Mercedes'],
            ['codigo_postal' => '5731', 'nombre' => 'Villa Mercedes'],
            ['codigo_postal' => '5733', 'nombre' => 'Justo Daract'],
            ['codigo_postal' => '5735', 'nombre' => 'La Punilla'],
            ['codigo_postal' => '5750', 'nombre' => 'Merlo'],
            ['codigo_postal' => '5752', 'nombre' => 'Villa del Carmen'],
            ['codigo_postal' => '5753', 'nombre' => 'Los Molles'],
            ['codigo_postal' => '5755', 'nombre' => 'Carpintería'],
            ['codigo_postal' => '5757', 'nombre' => 'Santa Rosa del Conlara'],
            ['codigo_postal' => '5759', 'nombre' => 'Concarán'],
            ['codigo_postal' => '5761', 'nombre' => 'Tilisarao'],
            ['codigo_postal' => '5767', 'nombre' => 'Naschel'],
            ['codigo_postal' => '5770', 'nombre' => 'La Toma'],
            ['codigo_postal' => '5777', 'nombre' => 'Candelaria'],
            ['codigo_postal' => '5773', 'nombre' => 'San Francisco del Monte de Oro'],
            ['codigo_postal' => '5775', 'nombre' => 'Quines'],
        ];

        foreach ($localidadesSanLuis as $loc) {
            $localidades[] = [
                'provincia_id' => $sanLuis,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SANTA CRUZ (AR-Z)
        $santaCruz = $provincias['AR-Z'];
        $localidadesSantaCruz = [
            ['codigo_postal' => '9400', 'nombre' => 'Río Gallegos'],
            ['codigo_postal' => '9401', 'nombre' => 'Río Gallegos'],
            ['codigo_postal' => '9405', 'nombre' => '28 de Noviembre'],
            ['codigo_postal' => '9407', 'nombre' => 'Río Turbio'],
            ['codigo_postal' => '9409', 'nombre' => 'El Calafate'],
            ['codigo_postal' => '9301', 'nombre' => 'El Chaltén'],
            ['codigo_postal' => '9011', 'nombre' => 'Caleta Olivia'],
            ['codigo_postal' => '9015', 'nombre' => 'Pico Truncado'],
            ['codigo_postal' => '9017', 'nombre' => 'Las Heras'],
            ['codigo_postal' => '9019', 'nombre' => 'Perito Moreno'],
            ['codigo_postal' => '9020', 'nombre' => 'Puerto Deseado'],
            ['codigo_postal' => '9022', 'nombre' => 'San Julián'],
            ['codigo_postal' => '9024', 'nombre' => 'Puerto San Julián'],
            ['codigo_postal' => '9030', 'nombre' => 'Puerto Santa Cruz'],
            ['codigo_postal' => '9040', 'nombre' => 'Comandante Luis Piedra Buena'],
            ['codigo_postal' => '9050', 'nombre' => 'Los Antiguos'],
        ];

        foreach ($localidadesSantaCruz as $loc) {
            $localidades[] = [
                'provincia_id' => $santaCruz,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SANTA FE (AR-S)
        $santaFe = $provincias['AR-S'];
        $localidadesSantaFe = [
            ['codigo_postal' => '3000', 'nombre' => 'Santa Fe'],
            ['codigo_postal' => '3001', 'nombre' => 'Santa Fe'],
            ['codigo_postal' => '3002', 'nombre' => 'Santa Fe'],
            ['codigo_postal' => '3003', 'nombre' => 'Santa Fe'],
            ['codigo_postal' => '3004', 'nombre' => 'Santa Fe'],
            ['codigo_postal' => '3005', 'nombre' => 'Santo Tomé'],
            ['codigo_postal' => '3009', 'nombre' => 'Sauce Viejo'],
            ['codigo_postal' => '3011', 'nombre' => 'Recreo'],
            ['codigo_postal' => '3014', 'nombre' => 'Esperanza'],
            ['codigo_postal' => '3016', 'nombre' => 'Rafaela'],
            ['codigo_postal' => '3017', 'nombre' => 'Rafaela'],
            ['codigo_postal' => '3020', 'nombre' => 'San Carlos Centro'],
            ['codigo_postal' => '3022', 'nombre' => 'San Carlos Nord'],
            ['codigo_postal' => '3024', 'nombre' => 'Pilar'],
            ['codigo_postal' => '3028', 'nombre' => 'Sunchales'],
            ['codigo_postal' => '3030', 'nombre' => 'María Juana'],
            ['codigo_postal' => '3034', 'nombre' => 'San Jorge'],
            ['codigo_postal' => '3036', 'nombre' => 'El Trébol'],
            ['codigo_postal' => '3040', 'nombre' => 'San Cristóbal'],
            ['codigo_postal' => '3046', 'nombre' => 'Suardi'],
            ['codigo_postal' => '3050', 'nombre' => 'Ceres'],
            ['codigo_postal' => '3056', 'nombre' => 'Hersilia'],
            ['codigo_postal' => '3060', 'nombre' => 'San Justo'],
            ['codigo_postal' => '3063', 'nombre' => 'Videla'],
            ['codigo_postal' => '3066', 'nombre' => 'Gobernador Crespo'],
            ['codigo_postal' => '3068', 'nombre' => 'San Javier'],
            ['codigo_postal' => '3070', 'nombre' => 'Reconquista'],
            ['codigo_postal' => '3071', 'nombre' => 'Reconquista'],
            ['codigo_postal' => '3074', 'nombre' => 'Avellaneda'],
            ['codigo_postal' => '3076', 'nombre' => 'Las Toscas'],
            ['codigo_postal' => '3080', 'nombre' => 'Villa Ocampo'],
            ['codigo_postal' => '3082', 'nombre' => 'Florencia'],
            ['codigo_postal' => '3084', 'nombre' => 'Villa Ana'],
            ['codigo_postal' => '3086', 'nombre' => 'Vera'],
            ['codigo_postal' => '2000', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2001', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2002', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2003', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2004', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2005', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2006', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2007', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2008', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2009', 'nombre' => 'Rosario'],
            ['codigo_postal' => '2013', 'nombre' => 'Pérez'],
            ['codigo_postal' => '2014', 'nombre' => 'Soldini'],
            ['codigo_postal' => '2016', 'nombre' => 'Zavalla'],
            ['codigo_postal' => '2017', 'nombre' => 'Piñero'],
            ['codigo_postal' => '2100', 'nombre' => 'San Nicolás de los Arroyos'],
            ['codigo_postal' => '2105', 'nombre' => 'San Lorenzo'],
            ['codigo_postal' => '2107', 'nombre' => 'Puerto General San Martín'],
            ['codigo_postal' => '2108', 'nombre' => 'Capitán Bermúdez'],
            ['codigo_postal' => '2109', 'nombre' => 'Fray Luis Beltrán'],
            ['codigo_postal' => '2111', 'nombre' => 'Roldán'],
            ['codigo_postal' => '2121', 'nombre' => 'Funes'],
            ['codigo_postal' => '2124', 'nombre' => 'Carcarañá'],
            ['codigo_postal' => '2126', 'nombre' => 'San Jerónimo Sud'],
            ['codigo_postal' => '2132', 'nombre' => 'Casilda'],
            ['codigo_postal' => '2134', 'nombre' => 'Arequito'],
            ['codigo_postal' => '2136', 'nombre' => 'Los Molinos'],
            ['codigo_postal' => '2142', 'nombre' => 'Acebal'],
            ['codigo_postal' => '2144', 'nombre' => 'Arroyo Seco'],
            ['codigo_postal' => '2152', 'nombre' => 'Granadero Baigorria'],
            ['codigo_postal' => '2154', 'nombre' => 'Ibarlucea'],
            ['codigo_postal' => '2200', 'nombre' => 'San Lorenzo'],
            ['codigo_postal' => '2252', 'nombre' => 'Galvez'],
            ['codigo_postal' => '2300', 'nombre' => 'Rafaela'],
            ['codigo_postal' => '2301', 'nombre' => 'Rafaela'],
            ['codigo_postal' => '2310', 'nombre' => 'Cañada de Gómez'],
            ['codigo_postal' => '2311', 'nombre' => 'Correa'],
            ['codigo_postal' => '2313', 'nombre' => 'Armstrong'],
            ['codigo_postal' => '2315', 'nombre' => 'Las Parejas'],
            ['codigo_postal' => '2317', 'nombre' => 'Las Rosas'],
            ['codigo_postal' => '2340', 'nombre' => 'Villa Constitución'],
            ['codigo_postal' => '2342', 'nombre' => 'Firmat'],
            ['codigo_postal' => '2344', 'nombre' => 'Rufino'],
            ['codigo_postal' => '2346', 'nombre' => 'Venado Tuerto'],
            ['codigo_postal' => '2353', 'nombre' => 'Wheelwright'],
            ['codigo_postal' => '2400', 'nombre' => 'San Francisco'],
        ];

        foreach ($localidadesSantaFe as $loc) {
            $localidades[] = [
                'provincia_id' => $santaFe,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // SANTIAGO DEL ESTERO (AR-G)
        $santiagoEstero = $provincias['AR-G'];
        $localidadesSantiagoEstero = [
            ['codigo_postal' => '4200', 'nombre' => 'Santiago del Estero'],
            ['codigo_postal' => '4201', 'nombre' => 'Santiago del Estero'],
            ['codigo_postal' => '4202', 'nombre' => 'Santiago del Estero'],
            ['codigo_postal' => '4206', 'nombre' => 'La Banda'],
            ['codigo_postal' => '4208', 'nombre' => 'La Banda'],
            ['codigo_postal' => '4212', 'nombre' => 'Termas de Río Hondo'],
            ['codigo_postal' => '4220', 'nombre' => 'Frías'],
            ['codigo_postal' => '4225', 'nombre' => 'Loreto'],
            ['codigo_postal' => '4230', 'nombre' => 'Añatuya'],
            ['codigo_postal' => '4234', 'nombre' => 'Bandera'],
            ['codigo_postal' => '4236', 'nombre' => 'Pinto'],
            ['codigo_postal' => '4238', 'nombre' => 'Fernández'],
            ['codigo_postal' => '4242', 'nombre' => 'Suncho Corral'],
            ['codigo_postal' => '4244', 'nombre' => 'Quimilí'],
            ['codigo_postal' => '4248', 'nombre' => 'Monte Quemado'],
            ['codigo_postal' => '4252', 'nombre' => 'Campo Gallo'],
            ['codigo_postal' => '4302', 'nombre' => 'Clodomira'],
            ['codigo_postal' => '4306', 'nombre' => 'Colonia Dora'],
            ['codigo_postal' => '4313', 'nombre' => 'Selva'],
            ['codigo_postal' => '4315', 'nombre' => 'Nueva Esperanza'],
            ['codigo_postal' => '4317', 'nombre' => 'Los Juríes'],
            ['codigo_postal' => '4321', 'nombre' => 'Villa Ojo de Agua'],
            ['codigo_postal' => '4324', 'nombre' => 'Sumampa'],
            ['codigo_postal' => '4326', 'nombre' => 'Ojo de Agua'],
            ['codigo_postal' => '4350', 'nombre' => 'Beltrán'],
        ];

        foreach ($localidadesSantiagoEstero as $loc) {
            $localidades[] = [
                'provincia_id' => $santiagoEstero,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // TIERRA DEL FUEGO (AR-V)
        $tierraFuego = $provincias['AR-V'];
        $localidadesTierraFuego = [
            ['codigo_postal' => '9410', 'nombre' => 'Ushuaia'],
            ['codigo_postal' => '9411', 'nombre' => 'Ushuaia'],
            ['codigo_postal' => '9420', 'nombre' => 'Río Grande'],
            ['codigo_postal' => '9421', 'nombre' => 'Río Grande'],
            ['codigo_postal' => '9430', 'nombre' => 'Tolhuin'],
        ];

        foreach ($localidadesTierraFuego as $loc) {
            $localidades[] = [
                'provincia_id' => $tierraFuego,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // TUCUMÁN (AR-T)
        $tucuman = $provincias['AR-T'];
        $localidadesTucuman = [
            ['codigo_postal' => '4000', 'nombre' => 'San Miguel de Tucumán'],
            ['codigo_postal' => '4001', 'nombre' => 'San Miguel de Tucumán'],
            ['codigo_postal' => '4002', 'nombre' => 'San Miguel de Tucumán'],
            ['codigo_postal' => '4003', 'nombre' => 'San Miguel de Tucumán'],
            ['codigo_postal' => '4004', 'nombre' => 'San Miguel de Tucumán'],
            ['codigo_postal' => '4006', 'nombre' => 'Yerba Buena'],
            ['codigo_postal' => '4007', 'nombre' => 'Yerba Buena'],
            ['codigo_postal' => '4101', 'nombre' => 'Las Talitas'],
            ['codigo_postal' => '4103', 'nombre' => 'Banda del Río Salí'],
            ['codigo_postal' => '4105', 'nombre' => 'Alderetes'],
            ['codigo_postal' => '4107', 'nombre' => 'El Manantial'],
            ['codigo_postal' => '4109', 'nombre' => 'Tafí Viejo'],
            ['codigo_postal' => '4111', 'nombre' => 'Tafí del Valle'],
            ['codigo_postal' => '4113', 'nombre' => 'Amaicha del Valle'],
            ['codigo_postal' => '4117', 'nombre' => 'Raco'],
            ['codigo_postal' => '4119', 'nombre' => 'El Cadillal'],
            ['codigo_postal' => '4122', 'nombre' => 'Concepción'],
            ['codigo_postal' => '4124', 'nombre' => 'Chicligasta'],
            ['codigo_postal' => '4126', 'nombre' => 'Aguilares'],
            ['codigo_postal' => '4128', 'nombre' => 'Juan Bautista Alberdi'],
            ['codigo_postal' => '4132', 'nombre' => 'Monteros'],
            ['codigo_postal' => '4134', 'nombre' => 'Famaillá'],
            ['codigo_postal' => '4136', 'nombre' => 'Lules'],
            ['codigo_postal' => '4142', 'nombre' => 'San Isidro de Lules'],
            ['codigo_postal' => '4144', 'nombre' => 'Simoca'],
            ['codigo_postal' => '4146', 'nombre' => 'La Cocha'],
            ['codigo_postal' => '4152', 'nombre' => 'Bella Vista'],
            ['codigo_postal' => '4158', 'nombre' => 'Trancas'],
            ['codigo_postal' => '4162', 'nombre' => 'Burruyacú'],
            ['codigo_postal' => '4168', 'nombre' => 'Graneros'],
            ['codigo_postal' => '4172', 'nombre' => 'Lamadrid'],
            ['codigo_postal' => '4178', 'nombre' => 'Leales'],
        ];

        foreach ($localidadesTucuman as $loc) {
            $localidades[] = [
                'provincia_id' => $tucuman,
                'codigo_postal' => $loc['codigo_postal'],
                'nombre' => $loc['nombre'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $localidades;
    }
}
