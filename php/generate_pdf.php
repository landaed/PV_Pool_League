<?php
require_once __DIR__ . '/vendor/autoload.php';  // Corrected file path with forward slash
require_once 'db_connect.php';

class MYPDF extends TCPDF {
    public function Header() {
        try {
            $image_file = 'assets/images/PV-Pool-League.png'; // Corrected file path with forward slash
            if (!file_exists($image_file)) {
                echo "Header Error: Image file not found at $image_file<br>";
            } else {
                $this->Image($image_file, 15, 10, 40, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 15, 'FALL 2024 Pool League Signups', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        } catch (Exception $e) {
            echo 'Header Error: ' . $e->getMessage() . '<br>';
        }
    }

    public function Footer() {
        try {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        } catch (Exception $e) {
            echo 'Footer Error: ' . $e->getMessage() . '<br>';
        }
    }
}

function createTable($pdf, $header, $data) {
    try {
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);
        $pdf->SetFont('', 'B');

        $w = array_fill(0, count($header), 180 / count($header));

        foreach($header as $col) {
            $pdf->Cell($w[array_search($col, $header)], 7, $col, 1, 0, 'C', 1);
        }
        $pdf->Ln();

        $pdf->SetFont('');
        foreach($data as $row) {
            foreach($header as $col) {
                $pdf->Cell($w[array_search($col, $header)], 6, $row[$col], 1);
            }
            $pdf->Ln();
        }
        $pdf->Ln(10);
    } catch (Exception $e) {
        echo 'Table Creation Error: ' . $e->getMessage() . '<br>';
    }
}

try {
    echo "Starting PDF generation...<br>";

    $session = 'FALL 2024';
    $query = "
        SELECT t.TeamName, t.RegistrationDate, t.HomeBarFirstPick, t.HomeBarSecondPick, t.DayDivision, 
               p.PlayerName, p.Email, p.Phone
        FROM SportsTeam t
        LEFT JOIN Player p ON t.TeamID = p.TeamID
        WHERE t.Session = ?
        ORDER BY t.TeamID, p.PlayerID
    ";

    $stmt = $db->prepare($query);
    if (!$stmt) {
        die('Database Error: Failed to prepare statement - ' . $db->error . '<br>');
    }
    echo "Statement prepared successfully.<br>";

    $stmt->bind_param('s', $session);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        die('Database Error: Failed to execute statement - ' . $stmt->error . '<br>');
    }

    if ($result->num_rows === 0) {
        die('No data found for this session.<br>');
    }

    echo "Data fetched successfully.<br>";

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'Team Name' => $row['TeamName'],
            'Registration Date' => $row['RegistrationDate'],
            'Home Bar Picks' => $row['HomeBarFirstPick'] . ', ' . $row['HomeBarSecondPick'],
            'Day Division' => $row['DayDivision'],
            'Player' => $row['PlayerName'],
            'Email' => $row['Email'] ?? 'No Email',
            'Phone' => $row['Phone'] ?? 'No Phone',
        ];
    }

    $stmt->close();

    echo "Preparing PDF...<br>";

    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 40, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->AddPage();

    // Table headers
    $headers = ['Team Name', 'Registration Date', 'Home Bar Picks', 'Day Division', 'Player', 'Email', 'Phone'];

    // Add table to PDF
    createTable($pdf, $headers, $data);

    echo "Outputting PDF...<br>";
    $pdf->Output('fall_2024_signups.pdf', 'I');
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '<br>';
}
?>
