<?php

// Mengatur zona waktu default ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

/**
 * Modul 6 OOP
 * Kelas UtbkSimulator
 * Bertanggung jawab untuk memuat data cutoff, memproses skor input, 
 * menghitung rata-rata, dan menampilkan hasil simulasi.
 */
class UtbkSimulator {

    // Properti Kelas
    private array $subjects = [];
    private array $cutoffData = [];
    private array $inputScores = [];
    private ?float $averageScore = null;
    private array $acceptanceResults = [];
    private string $errorMessage = '';

    /**
     * Modul 4 Method dan function
     * Konstruktor Kelas. Menginisialisasi daftar subjek dan memuat data cutoff.
     */
    public function __construct() {
        // Modul 1 tipe data array
        // Daftar subjek UTBK
        $this->subjects = [
            'PU' => 'Penalaran Umum',
            'PPU' => 'Pengetahuan dan Pemahaman Umum',
            'PM' => 'Penalaran Matematika',
            'PBM' => 'Pemahaman Bacaan dan Menulis',
            'LITERASI_INDO' => 'Literasi dalam Bahasa Indonesia',
            'LITERASI_INGGRIS' => 'Literasi dalam Bahasa Inggris',
            'PK' => 'Pengetahuan Kuantitatif'
        ];
        
        // Memuat data cutoff saat objek dibuat
        $this->loadCutoffData();

        // Menginisialisasi inputScores untuk diisi di formulir
        foreach ($this->subjects as $key => $name) {
            $this->inputScores[$key] = '';
        }
    }

    /**
     * Modul 4 Function dan Method
     * Memuat data skor minimum (cutoff) dari file JSON eksternal.
     * Mengandung validasi file dan parsing JSON.
     */
    private function loadCutoffData(): void {
        $filePath = 'skor.json';
        $data = [];

        // Modul 2 Pengkondisian: Cek keberadaan file
        if (!file_exists($filePath)) {
            $this->errorMessage = "Error: File data cutoff ('$filePath') tidak ditemukan. Pastikan file skor.json ada di direktori yang sama.";
            return;
        }

        $jsonContent = file_get_contents($filePath);
        
        // Modul 2 Pengkondisian: Cek kegagalan baca file
        if ($jsonContent === false || empty($jsonContent)) {
            $this->errorMessage = "Error: Gagal membaca isi file JSON atau file kosong.";
            return;
        }

        $rawArray = json_decode($jsonContent, true);

        // Modul 2 Pengkondisian: Cek error JSON parse
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errorMessage = "Error saat mengurai JSON: " . json_last_error_msg();
            return;
        }

        // Modul 3 perulangan: Memproses setiap item data cutoff
        foreach ($rawArray as $item) {
            $university = $item['Universitas'] ?? '';
            $major = $item['JURUSAN'] ?? '';
            $rawScore = $item['SKOR UTBK'] ?? '0';

            // Modul 2 pengkondisian: Skip data yang tidak lengkap
            if (empty($university) || empty($major)) {
                continue;
            }

            // Membersihkan skor dari tanda kurung dan mengubah koma menjadi titik untuk konversi float
            $cleanScore = str_replace(['(', ')'], '', $rawScore);
            $cleanScore = str_replace(',', '.', $cleanScore);
            
            $minScore = is_numeric($cleanScore) ? (float)$cleanScore : 0.0;
            
            // Modul 2 Pengkondisian: Hanya simpan skor yang valid (> 0)
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

    /**
     * Modul 4 Method dan Function
     * Memproses permintaan POST dari formulir input skor.
     */
    public function processPostRequest(): void {
        // Modul 2 pengkondisian: Hanya memproses POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Modul 2 pengkondisian: Penanganan tombol reset (redirect untuk membersihkan state)
        if (isset($_POST['action']) && $_POST['action'] === 'reset') {
            // Melakukan redirect agar state form bersih
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $totalScore = 0;
        $scoreCount = 0;
        $formData = $_POST['scores'] ?? [];

        // Modul 3 Pengulangan: Memvalidasi dan mengakumulasi skor input
        foreach ($this->subjects as $key => $name) {
            $score = $formData[$key] ?? 0;
            
            // Modul 2 Pengkondisian: Validasi skor numerik
            if (is_numeric($score)) {
                // Batasi skor antara 0 dan 1000
                $validScore = (int)max(0, min(1000, $score)); 
                
                $this->inputScores[$key] = $validScore;
                $totalScore += $validScore;
                $scoreCount++;
            }
        }

        // Modul 2 Pengkondisian: Cek kelengkapan input
        if ($scoreCount !== count($this->subjects)) {
            if (empty($this->errorMessage)) {
                $this->errorMessage = "Error: Mohon isi semua " . count($this->subjects) . " kolom skor dengan angka antara 0-1000.";
            }
        } else {
            // Menghitung rata-rata skor
            $this->averageScore = round($totalScore / $scoreCount, 2);

            if (empty($this->cutoffData) && empty($this->errorMessage)) {
                 $this->errorMessage = "Peringatan: Data skor minimum kosong atau tidak ada skor yang valid dalam file 'skor.json'.";
            }
            
            // Membandingkan rata-rata skor dengan cutoff
            // Modul 3 Pengulangan: Iterasi data cutoff
            foreach ($this->cutoffData as $item) {
                $minScore = $item['min_score'];
                $scoreDiff = $this->averageScore - $minScore;

                // Modul 2 Pengkondisian: Hanya simpan jurusan yang "Lulus" (Skor Rata-rata >= Skor Minimum)
                if ($scoreDiff >= 0) {
                    $this->acceptanceResults[] = [
                        'university' => $item['university'],
                        'major' => $item['major'],
                        'min_score' => $minScore,
                        'score_diff' => round($scoreDiff, 2),
                    ];
                }
            }
            
            // Mengurutkan hasil berdasarkan min_score tertinggi
            usort($this->acceptanceResults, function($a, $b) {
                // Menggunakan Operator Spaceship (PHP 7+)
                return $b['min_score'] <=> $a['min_score'];
            });
            
            // Ambil 10 hasil teratas
            $this->acceptanceResults = array_slice($this->acceptanceResults, 0, 10);
        }
    }

    /**
     * Modul 5 Method dan Function
     * Mengembalikan pesan selisih skor yang diformat.
     * * @param float $scoreDiff Selisih antara rata-rata skor dan skor minimum.
     * @return string Pesan yang menunjukkan selisih positif atau negatif.
     */
    public function getScoreDifferenceMessage(float $scoreDiff): string {
        $formattedDiff = number_format(abs($scoreDiff), 2);
        return $scoreDiff >= 0 ? "Selisih: + " . $formattedDiff . " Poin" : "Selisih: - " . $formattedDiff . " Poin";
    }

    /**
     * Modul 4 Method dan Function
     * Merender tampilan HTML/GUI.
     */
    public function renderHtml(): void {
        $averageScore = $this->averageScore;
        $errorMessage = $this->errorMessage;
        $acceptanceResults = $this->acceptanceResults;
        $inputScores = $this->inputScores;
        $subjects = $this->subjects;

    // Modul 8 GUI (HTML dengan Tailwind CSS dan JavaScript)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulator UTBK (OOP) & Login</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Mengimpor font Inter */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #121212; 
            color: #e0e0e0;
        }
        /* Styling Card */
        .card {
            background-color: #1f1f1f;
            border: 1px solid #333;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -2px rgba(0, 0, 0, 0.5);
        }
        /* Styling Tombol Primer */
        .btn-primary {
            background-image: linear-gradient(to right, #4F46E5, #3B82F6);
        }
        .btn-secondary {
            background-color: #374151; /* Gray-700 */
        }
        .btn-secondary:hover {
            background-color: #4b5563; /* Gray-600 */
        }
        /* Styling Tabel Hasil */
        .result-table td {
            border-bottom: 1px solid #2a2a2a;
            padding: 12px;
        }
        .result-table th {
            text-align: left;
            background-color: #2a2a2a;
            padding: 12px;
        }
        
        /* Menyembunyikan panah/spinners pada input number */
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

        <!-- START: Kontainer Login -->
        <div id="login-container" class="max-w-md mx-auto card p-6 rounded-xl hidden">
            <h2 class="text-2xl font-bold mb-6 text-white text-center">Login Siswa</h2>
            <form id="login-form">
                <div class="mb-4">
                    <label for="nisn" class="block text-gray-300 text-sm font-semibold mb-2">NISN (10 Digit)</label>
                    <input type="text" id="nisn" required minlength="10" maxlength="10"
                        class="shadow appearance-none border border-gray-700 rounded-lg w-full py-3 px-4 bg-gray-700 text-white leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out"
                        placeholder="Masukkan NISN Anda">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-300 text-sm font-semibold mb-2">Password</label>
                    <input type="password" id="password" required minlength="6"
                        class="shadow appearance-none border border-gray-700 rounded-lg w-full py-3 px-4 bg-gray-700 text-white leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out"
                        placeholder="Masukkan Password">
                </div>
                <button type="submit" 
                    class="btn-primary w-full text-white font-bold py-3 px-4 rounded-lg hover:opacity-90 transition duration-150 ease-in-out">
                    MASUK
                </button>
                <p id="login-error" class="text-sm text-red-500 mt-3 hidden text-center">NISN harus 10 digit dan Password minimal 6 karakter.</p>
                <p id="logged-in-message" class="text-sm text-green-500 mt-3 hidden text-center">Login Berhasil! Selamat datang, <span id="nisn-display"></span>.</p>
            </form>
        </div>
        <!-- END: Kontainer Login -->

        <!-- START: Kontainer Utama Simulator (Disembunyikan sampai Login) -->
        <div id="simulator-main-content" class="hidden">
            <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Kolom 1: Form Input Skor -->
                <div class="lg:col-span-1 card p-6 rounded-xl h-fit">
                    <h2 class="text-xl font-bold mb-6 text-white border-b border-gray-700 pb-3">Input Skor Mata Pelajaran</h2>
                    <!-- Form utama untuk pengiriman data -->
                    <form id="utbk-form" method="POST" action="">
                        <!-- Modul 3 Perulangan untuk membuat semua field input -->
                        <?php foreach ($subjects as $key => $name): ?>
                            <div class="mb-4">
                                <label for="<?= $key ?>" class="block text-gray-300 text-sm font-semibold mb-2"><?= $name ?></label>
                                <input type="number" name="scores[<?= $key ?>]" id="<?= $key ?>" min="0" max="1000"
                                    inputmode="numeric" 
                                    value="<?= htmlspecialchars((string)($inputScores[$key] ?? '')) ?>"
                                    required
                                    class="score-input shadow appearance-none border border-gray-700 rounded-lg w-full py-3 px-4 bg-gray-700 text-white leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out">
                                <p class="text-xs text-gray-500 mt-1">Skor harus antara 0 hingga 1000.</p>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-6 flex space-x-4">
                            <button type="submit" name="action" value="reset" 
                                        class="btn-secondary w-1/3 text-white font-bold py-3 px-4 rounded-lg transition duration-150 ease-in-out">
                                Reset
                            </button>
                            <button type="submit" name="action" value="calculate"
                                        id="submit-button"
                                        class="btn-primary w-2/3 text-white font-bold py-3 px-4 rounded-lg hover:bg-indigo-600 transition duration-150 ease-in-out">
                                Hitung Rata-Rata
                            </button>
                        </div>
                    </form>
                    <button id="logout-button" class="mt-4 w-full text-sm text-red-400 hover:text-red-300">Logout</button>
                </div>

                <!-- Kolom 2 & 3: Hasil dan Pesan -->
                <div class="lg:col-span-2 space-y-8">
                    <?php if (!empty($errorMessage)): ?>
                    <!-- Pesan Error/Peringatan -->
                        <div class="bg-red-900/50 border border-red-700 p-4 rounded-lg text-red-300 rounded-xl">
                            <p class="font-semibold">Kesalahan Data/Pemrosesan:</p>
                            <p><?php echo htmlspecialchars($errorMessage); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($averageScore !== null && empty($errorMessage)): ?>
                    <!-- Hasil Rata-Rata Skor -->
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

                        <!-- Hasil Jurusan Lulus -->
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
                                            // Modul 3 Perulangan untuk menampilkan hasil
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
                                                    <!-- Modul 5 - Menampilkan hasil fungsi getScoreDifferenceMessage -->
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
                            <!-- Modul 2 Pengkondisian: Jika tidak ada jurusan yang lulus -->
                                <div class="bg-yellow-900/50 border border-yellow-700 p-4 rounded-lg text-yellow-300 rounded-xl">
                                    <p class="font-semibold">Mohon Maaf!</p>
                                    <p>Skor rata-rata Anda belum mencapai Skor Minimum untuk jurusan yang terdata dalam simulasi ini. Tingkatkan skor Anda!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif (empty($errorMessage)): ?>
                    <!-- Pesan Awal Sebelum Input -->
                        <div class="card p-8 text-center rounded-xl lg:col-span-3">
                            <svg class="w-16 h-16 mx-auto mb-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v14m-6 3h6a2 2 0 002-2v-3m-6-1V3a1 1 0 011-1h2a1 1 0 011 1v12m-8 3v-2m-1 0H4a1 1 0 01-1-1V9a1 1 0 011-1h3m-1 0V4"></path></svg>
                            <h2 class="text-2xl font-semibold text-white">Mulai Analisis Skor UTBK Anda</h2>
                            <p class="text-gray-400 mt-2">Masukkan 7 skor mata pelajaran Anda di formulir sebelah kiri, lalu tekan tombol "Hitung Rata-Rata".</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        <!-- END: Kontainer Utama Simulator -->
        
        <footer class="text-center mt-12 text-sm text-gray-600 border-t border-gray-800 pt-6">
            <p></p>
            <p></p>
        </footer>
    </div>
    
    <!-- JavaScript untuk Logic Login Gate (Menggunakan Local Storage) -->
    <script>
        const LOGIN_KEY = 'utbk_simulator_logged_in';
        const NISN_KEY = 'utbk_simulator_nisn';
        const loginContainer = document.getElementById('login-container');
        const simulatorContent = document.getElementById('simulator-main-content');
        const loginForm = document.getElementById('login-form');
        const loginError = document.getElementById('login-error');
        const nisnDisplay = document.getElementById('nisn-display');
        const loggedInMessage = document.getElementById('logged-in-message');
        const logoutButton = document.getElementById('logout-button');

        // Fungsi untuk memeriksa dan menampilkan konten berdasarkan status login
        function checkLoginStatus() {
            if (localStorage.getItem(LOGIN_KEY) === 'true') {
                const nisn = localStorage.getItem(NISN_KEY) || 'Siswa';
                nisnDisplay.textContent = nisn;
                loginContainer.classList.add('hidden');
                simulatorContent.classList.remove('hidden');
                loggedInMessage.classList.remove('hidden');
            } else {
                loginContainer.classList.remove('hidden');
                simulatorContent.classList.add('hidden');
                loggedInMessage.classList.add('hidden');
            }
        }

        // Event listener untuk submit form login
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const nisnInput = document.getElementById('nisn').value;
            const passwordInput = document.getElementById('password').value;
            
            // Validasi dummy
            if (nisnInput.length === 10 && passwordInput.length >= 6) {
                // Login Berhasil
                localStorage.setItem(LOGIN_KEY, 'true');
                localStorage.setItem(NISN_KEY, nisnInput);
                loginError.classList.add('hidden');
                checkLoginStatus();
            } else {
                // Login Gagal
                loginError.classList.remove('hidden');
                loginError.textContent = "NISN harus 10 digit dan Password minimal 6 karakter.";
            }
        });
        
        // Event listener untuk tombol logout
        logoutButton.addEventListener('click', function() {
            localStorage.removeItem(LOGIN_KEY);
            localStorage.removeItem(NISN_KEY);
            // Reset input fields login
            document.getElementById('nisn').value = '';
            document.getElementById('password').value = '';
            checkLoginStatus();
            // Redirect atau muat ulang halaman untuk membersihkan hasil simulasi
            window.location.href = window.location.pathname;
        });

        // Panggil saat halaman dimuat
        checkLoginStatus();
    </script>
</body>
</html>
<?php
    }
}


// --- Logika Eksekusi Utama PHP ---
// 1. Instansiasi Class (Memuat data cutoff di konstruktor)
$simulator = new UtbkSimulator();

// 2. Proses data POST (jika formulir disubmit)
$simulator->processPostRequest();

// 3. Render HTML
$simulator->renderHtml();

?>