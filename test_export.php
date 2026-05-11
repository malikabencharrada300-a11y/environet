<?php
// Test simple d'export CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="test.csv"');

echo "Colonne1;Colonne2;Colonne3\n";
echo "Valeur1;Valeur2;Valeur3\n";
echo "Test1;Test2;Test3\n";
exit;
?>