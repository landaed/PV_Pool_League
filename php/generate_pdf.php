<?php
// Start output buffering immediately to prevent any accidental output
ob_start();

require_once '/home3/pvd001/public_html/vendor/autoload.php';  // Correct path to include the autoload.php file from one directory up
require_once 'db_connect.php';

class MYPDF extends TCPDF {
    public function Header() {
        try {
            $image_file = __DIR__ . '/assets/images/PV-Pool-League.png'; // Absolute path to the image file
            if (file_exists($image_file)) {
                $this->Image($image_file, 15, 10, 40, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            } 
            $this->SetFont('helvetica', 'B', 12);
            $this->Cell(0, 15, 'FALL 2024 Pool League Signups', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        } catch (Exception $e) {
            // Suppress output, use error logging if necessary
        }
    }

    public function Footer() {
        try {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        } catch (Exception $e) {
            // Suppress output, use error logging if necessary
        }
    }
}

function createTable($pdf, $header, $data) {
    try {
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);
        $pdf->SetFont('', 'B');

        // Define custom widths for each column based on the new page width
        $w = array_fill(0, count($header), 470 / count($header)); // Adjusted width for wider page

        foreach ($header as $col) {
            $pdf->Cell($w[array_search($col, $header)], 7, $col, 1, 0, 'C', 1);
        }
        $pdf->Ln();

        $pdf->SetFont('');
        foreach ($data as $row) {
            foreach ($header as $col) {
                $pdf->Cell($w[array_search($col, $header)], 6, $row[$col], 1);
            }
            $pdf->Ln();
        }
        $pdf->Ln(10);
    } catch (Exception $e) {
        // Suppress output, use error logging if necessary
    }
}

try {
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
        // Handle error appropriately without sending output
        exit;
    }

    $stmt->bind_param('s', $session);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        // Handle error appropriately without sending output
        exit;
    }

    if ($result->num_rows === 0) {
        // Handle case of no data found appropriately
        exit;
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'Team Name' => $row['TeamName'],
            'Registration Date' => $row['RegistrationDate'],
            // Modify the assignment to limit the string length to 18 characters
            'Home Bar Picks' => substr($row['HomeBarFirstPick'] . ', ' . $row['HomeBarSecondPick'], 0, 28),

            'Day Division' => $row['DayDivision'],
            'Player' => $row['PlayerName'],
            'Email' => $row['Email'] ?? 'No Email',
            'Phone' => $row['Phone'] ?? 'No Phone',
        ];
    }

    $stmt->close();

    // Set custom page size: A4 with increased width
    $pdf = new MYPDF('L', PDF_UNIT, [497, 210], true, 'UTF-8', false);  // 'L' for landscape, A4 width is 297mm, height 210mm
    $pdf->SetMargins(10, 40, 10);  // Set smaller margins for wider content display
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->AddPage();

    // Table headers
    $headers = ['Team Name', 'Registration Date', 'Home Bar Picks', 'Day Division', 'Player', 'Email', 'Phone'];

    // Add table to PDF
    createTable($pdf, $headers, $data);

    // End output buffering before sending PDF
    ob_end_clean();
    $pdf->Output('fall_2024_signups.pdf', 'I');
} catch (Exception $e) {
    // Handle errors appropriately without sending output
    ob_end_clean();
}
?>
