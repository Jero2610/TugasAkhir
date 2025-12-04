<?php

date_default_timezone_set('Asia/Jakarta');

// Modul 6 OOP 
class UtbkSimulator {

    private array $subjects = [];
    private array $cutoffData = [];
    private array $inputScores = [];
    private ?float $averageScore = null;
    private array $acceptanceResults = [];
    private string $errorMessage = '';

    // Modul 4 Method dan function
        public function __construct() {
    //Modul 1 tipe data array
        $this->subjects = [
            'PU' => 'Penalaran Umum',
            'PPU' => 'Pengetahuan dan Pemahaman Umum',
            'PM' => 'Penalaran Matematika',
            'PBM' => 'Pemahaman Bacaan dan Menulis',
            'LITERASI_INDO' => 'Literasi dalam Bahasa Indonesia',
            'LITERASI_INGGRIS' => 'Literasi dalam Bahasa Inggris',
            'PK' => 'Pengetahuan Kuantitatif'
        ];
        
        $this->loadCutoffData();

        foreach ($this->subjects as $key => $name) {
            $this->inputScores[$key] = '';
        }
    }

    // Modul 4 Function dan Method
    private function loadCutoffData(): void {
        $filePath = 'skor.json';
        $data = [];

     //Modul 2 Pengkondisian
        if (!file_exists($filePath)) {
            $this->errorMessage = "Error: File data cutoff ('$filePath') tidak ditemukan.";
            return;
        }

        $jsonContent = file_get_contents($filePath);
        
      
        if ($jsonContent === false || empty($jsonContent)) {
            $this->errorMessage = "Error: Gagal membaca isi file JSON.";
            return;
        }

        $rawArray = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorMessage = "Error saat mengurai JSON: " . json_last_error_msg();
            return;
        }

      //Modul 3 perulangan
        foreach ($rawArray as $item) {
            $university = $item['Universitas'] ?? '';
            $major = $item['JURUSAN'] ?? '';
            $rawScore = $item['SKOR UTBK'] ?? '0';

            //Modul 2 pengkondisian
            if (empty($university) || empty($major)) {
                continue;
            }

            $cleanScore = str_replace(['(', ')'], '', $rawScore);
            $cleanScore = str_replace(',', '.', $cleanScore);
            
         
            $minScore = is_numeric($cleanScore) ? (float)$cleanScore : 0.0;
            
            // Modul 2 Pengkondisian
            if ($minScore > 0) {
                $data[] = [
                    'university' => $university,
                    'major' => $major,
                    'min_score' => $minScore,
                ];
            }
        }
        $this->cutoffData = $data;
    }

    // Modul 4 Method dan Function
    public function processPostRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

    // Modul 2 pengkondisian
        if (isset($_POST['action']) && $_POST['action'] === 'reset') {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $totalScore = 0;
        $scoreCount = 0;
        $formData = $_POST['scores'] ?? [];

        // Modul 3 Pengulangan
        foreach ($this->subjects as $key => $name) {
            //Modul 2 Pengkondisian
            $score = $formData[$key] ?? 0;
            if (is_numeric($score)) {
                $validScore = (int)max(0, min(1000, $score)); 
                
                $this->inputScores[$key] = $validScore;
                $totalScore += $validScore;
                $scoreCount++;
            }
        }

        //Modul 2 Pengkondisian
        if ($scoreCount !== count($this->subjects)) {
            if (empty($this->errorMessage)) {
                $this->errorMessage = "Error: Mohon isi semua " . count($this->subjects) . " kolom skor dengan angka antara 0-1000.";
            }
        } elseif (empty($this->cutoffData) && empty($this->errorMessage)) {
             
        } else {
           
            $this->averageScore = round($totalScore / $scoreCount, 2);

           
            foreach ($this->cutoffData as $item) {
                $minScore = $item['min_score'];
                $scoreDiff = $this->averageScore - $minScore;

                // Modul 2 Pengkondisian
                if ($scoreDiff >= 0) {
                    $this->acceptanceResults[] = [
                        'university' => $item['university'],
                        'major' => $item['major'],
                        'min_score' => $minScore,
                        'score_diff' => round($scoreDiff, 2),
                    ];
                }
            }
            
           
            usort($this->acceptanceResults, function($a, $b) {
              
                return $b['min_score'] <=> $a['min_score'];
            });
            
          
            $this->acceptanceResults = array_slice($this->acceptanceResults, 0, 10);
        }
    }

    //Modul 5 Method dan Function
    public function getScoreDifferenceMessage(float $scoreDiff): string {
        $formattedDiff = number_format(abs($scoreDiff), 2);
        return $scoreDiff >= 0 ? "Selisih: + " . $formattedDiff . " Poin" : "Selisih: - " . $formattedDiff . " Poin";
    }

   
    public function renderHtml(): void {
        $averageScore = $this->averageScore;
        $errorMessage = $this->errorMessage;
        $acceptanceResults = $this->acceptanceResults;
        $inputScores = $this->inputScores;
        $subjects = $this->subjects;

      
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulator UTBK (OOP)</title>
 
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #121212; 
            color: #e0e0e0;
        }
        .card {
            background-color: #1f1f1f;
            border: 1px solid #333;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -2px rgba(0, 0, 0, 0.5);
        }
        .btn-primary {
            background-image: linear-gradient(to right, #4F46E5, #3B82F6);
        }
        .btn-secondary {
            background-color: #374151; /* Gray-700 */
        }
        .btn-secondary:hover {
            background-color: #4b5563; /* Gray-600 */
        }
        .result-table td {
            border-bottom: 1px solid #2a2a2a;
            padding: 12px;
        }
        .result-table th {
            text-align: left;
            background-color: #2a2a2a;
            padding: 12px;
        }
       
        input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>
<body class="p-4 md:p-8 min-h-screen">

    <div class="max-w-7xl mx-auto">
        <header class="text-center py-6 mb-8 card rounded-xl">
            <h1 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight">
                <span class="text-indigo-400">UTBK</span> Simulasi Skor
            </h1>
            <p class="text-gray-400 mt-2">Menampilkan Jurusan yang direkomendasikan berdasarkan skor rata-rata.</p>
        </header>

        <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Kolom 1: Form Input Skor (4. GUI) -->
            <div class="lg:col-span-1 card p-6 rounded-xl h-fit">
                <h2 class="text-xl font-bold mb-6 text-white border-b border-gray-700 pb-3">Input Skor Mata Pelajaran</h2>
                <!-- Form utama untuk pengiriman data -->
                <form id="utbk-form" method="POST" action="">
                    <!-- 1. Perulangan untuk membuat semua field input -->
                    <?php foreach ($subjects as $key => $name): ?>
                        <div class="mb-4">
                            <label for="<?= $key ?>" class="block text-gray-300 text-sm font-semibold mb-2"><?= $name ?></label>
                            <input type="number" name="scores[<?= $key ?>]" id="<?= $key ?>" min="0" max="1000"
                                inputmode="numeric" 
                                value="<?= htmlspecialchars((string)($inputScores[$key] ?? '')) ?>"
                                required
                                class="score-input shadow appearance-none border border-gray-700 rounded w-full py-3 px-4 bg-gray-700 text-white leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out">
                            <p class="text-xs text-gray-500 mt-1">Skor harus antara 0 hingga 1000.</p>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-6 flex space-x-4">
                        <button type="submit" name="action" value="reset" 
                                class="btn-secondary w-1/3 text-white font-bold py-3 px-4 rounded-lg rounded-xl transition duration-150 ease-in-out">
                            Reset
                        </button>
                        <button type="submit" name="action" value="calculate"
                                id="submit-button"
                                class="btn-primary w-2/3 text-white font-bold py-3 px-4 rounded-lg rounded-xl hover:bg-indigo-600 transition duration-150 ease-in-out">
                            Hitung Rata-Rata
                        </button>
                    </div>
                </form>
            </div>

           
            <div class="lg:col-span-2 space-y-8">
                <?php if (!empty($errorMessage)): ?>
             
                    <div class="bg-red-900/50 border border-red-700 p-4 rounded-lg text-red-300 rounded-xl">
                        <p class="font-semibold">Kesalahan Data/Pemrosesan:</p>
                        <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($averageScore !== null && empty($errorMessage)): ?>
             
                    <div class="card p-6 rounded-xl">
                        <h2 class="text-2xl font-bold mb-4 text-white border-b border-indigo-500 pb-2">
                            Rata-Rata Skor UTBK Anda
                        </h2>
                        <div class="bg-gray-800 p-4 rounded-lg rounded-xl">
                            <p class="text-lg font-semibold text-gray-300">Total Rata-Rata Sederhana:</p>
                            <p class="text-4xl font-extrabold text-yellow-400 mt-1">
                                <?php echo number_format($averageScore, 2); ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-1">Dihitung dari <?= count($subjects) ?> subtes.</p>
                        </div> 
                    </div>

                   
                    <div class="card p-6 rounded-xl">
                        <h2 class="text-2xl font-bold mb-4 text-white border-b border-green-500 pb-2">
                            Top 10 Jurusan Lulus 
                        </h2>

                        <?php if (!empty($acceptanceResults)): ?>
                            
                            <div class="overflow-x-auto">
                                <table class="result-table w-full text-sm rounded-lg overflow-hidden">
                                    <thead>
                                        <tr>
                                            <th class="w-1/4">Universitas</th>
                                            <th class="w-1/2">Jurusan</th>
                                            <th class="w-1/4 text-left">Skor Minimum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                  
                                        <?php 
                                        $rank = 1;
                                        foreach ($acceptanceResults as $result): 
                                   
                                            $scoreDiffMsg = $this->getScoreDifferenceMessage($result['score_diff']);
                                        ?>
                                        <tr class="hover:bg-gray-800">
                                            <td class="font-medium text-white">
                                                <span class="inline-block w-4 text-indigo-400 font-bold mr-2"><?= $rank++ ?>.</span>
                                                <?= htmlspecialchars($result['university']); ?>
                                            </td>
                                            <td>
                                                <div class="font-medium text-gray-300"><?= htmlspecialchars($result['major']); ?></div>
                                                <div class="text-xs text-green-500"><?= $scoreDiffMsg; ?></div>
                                            </td>
                                            <td class="text-yellow-400 font-mono text-left"><?= number_format($result['min_score'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-4 text-sm text-gray-500">Menampilkan 10 jurusan teratas, diurutkan dari Skor Minimum tertinggi hingga terendah.</p>
                        <?php else: ?>

                            <div class="bg-red-900/50 border border-red-700 p-4 rounded-lg text-red-300 rounded-xl">
                                <p class="font-semibold">Mohon Maaf!</p>
                                <p>Skor rata-rata Anda belum mencapai Skor Minimum untuk jurusan yang terdata dalam simulasi ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (empty($errorMessage)): ?>
                    <div class="card p-8 text-center rounded-xl lg:col-span-3">
                        <svg class="w-16 h-16 mx-auto mb-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v14m-6 3h6a2 2 0 002-2v-3m-6-1V3a1 1 0 011-1h2a1 1 0 011 1v12m-8 3v-2m-1 0H4a1 1 0 01-1-1V9a1 1 0 011-1h3m-1 0V4"></path></svg>
                        <h2 class="text-2xl font-semibold text-white">Mulai Analisis Skor UTBK Anda</h2>
                        <p class="text-gray-400 mt-2">Masukkan 7 skor mata pelajaran Anda di formulir sebelah kiri, lalu tekan tombol "Hitung Rata-Rata".</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <footer class="text-center mt-12 text-sm text-gray-600 border-t border-gray-800 pt-6">
            <p>Simulator UTBK.</p>
        </footer>
    </div>
    
</body>
</html>
<?php
    }
}


$simulator = new UtbkSimulator();

$simulator->processPostRequest();

$simulator->renderHtml();


?>