<?php
// Function to sanitize the folder name input
function sanitize_folder($folder) {
    return filter_var($folder, FILTER_SANITIZE_STRING);
}

// XOR encryption/decryption function
function xor_encrypt_decrypt($data, $key) {
    $keyLength = strlen($key);
    $output = '';

    for ($i = 0; $i < strlen($data); $i++) {
        $output .= $data[$i] ^ $key[$i % $keyLength];
    }

    return $output;
}

// Check if the download GET parameter is set
if (isset($_GET['download']) && $_GET['download'] == 1 && isset($_GET['folder'])) {
    // Sanitize the folder input
    $selectedFolder = sanitize_folder($_GET['folder']);
    
    // Database configuration
    $dbHost = 'localhost';
    $dbUser = 'afnan';
    $dbPass = 'john_wick_77';
    $dbName = 'mywebsite_images';
    $encryptionKey = '123'; // Replace with your actual key

    // Create a database connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Query to retrieve encrypted image data from the selected folder table
    $sql = "SELECT id, images FROM $selectedFolder";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Create a temporary directory for storing images
        $tempDir = sys_get_temp_dir() . '/' . uniqid('images_') . '/';
        if (!mkdir($tempDir) && !is_dir($tempDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }

        while ($row = $result->fetch_assoc()) {
            $imageId = $row["id"];
            $encryptedImageData = $row["images"];

            // Decrypt the image data
            $decryptedImageData = xor_encrypt_decrypt($encryptedImageData, $encryptionKey);

            // Save the decrypted image to the temporary directory
            $imageFileName = $tempDir . 'image_' . $imageId . '.jpg';
            file_put_contents($imageFileName, $decryptedImageData);
        }

        // Create a ZIP file containing all images
        $zipFileName = sys_get_temp_dir() . '/' . uniqid('images_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFileName, ZipArchive::CREATE) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir), RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $file) {
                // Skip directories (they would be added automatically)
                if (!$file->isDir()) {
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($tempDir));

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);
                }
            }
            // Zip archive will be created only after closing object
            $zip->close();

            // Send the ZIP file for download
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="'.basename($zipFileName).'"');
            header('Content-Length: ' . filesize($zipFileName));
            flush();
            readfile($zipFileName);

            // Clean up temporary files and directory
            array_map('unlink', glob("$tempDir*.*"));
            rmdir($tempDir);
            unlink($zipFileName);
            exit();
        } else {
            echo "Failed to create the ZIP file.";
        }
    } else {
        echo "No images found in $selectedFolder.";
    }

    $conn->close();
}

// Check if the server request method is GET and view_images is set
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['view_images'])) {
    // Sanitize the folder input
    $selectedFolder = sanitize_folder($_GET['folder']);
    
    // Database configuration
    $dbHost = 'localhost';
    $dbUser = 'afnan';
    $dbPass = 'john_wick_77';
    $dbName = 'mywebsite_images';
    $encryptionKey = '123'; // Replace with your actual key

    // Create a database connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Query to retrieve encrypted image data from the selected folder table
    $sql = "SELECT id, images FROM $selectedFolder";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Output the images
        while ($row = $result->fetch_assoc()) {
            $imageId = $row["id"];
            $encryptedImageData = $row["images"];

            // Decrypt the image data
            $decryptedImageData = xor_encrypt_decrypt($encryptedImageData, $encryptionKey);

            $base64Image = base64_encode($decryptedImageData);
            echo "<div class='image-item'>";
            echo "<h2>Image $imageId</h2>";
            echo "<img src='data:image/jpeg;base64,$base64Image' alt='Image $imageId'>";
            echo "</div>";
        }
    } else {
        echo "No images found in $selectedFolder.";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Image Upload and Viewer</title>
</head>
<body>
    <!-- HTML form for image upload -->
    <h1>Upload Images</h1>
    <form action="index.php" method="POST" enctype="multipart/form-data">
        <label for="image">Choose image(s) to upload:</label>
        <input type="file" name="image[]" id="image" accept="image/*" multiple>
        <br>
        <label for="folder">Select a folder:</label>
        <select name="folder" id="folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <br>
        <input type="submit" value="Upload">
    </form>

    <!-- HTML form for image viewing -->
    <h1>View Images</h1>
    <form action="index.php" method="GET">
        <label for="view_folder">Select a folder to view images:</label>
        <select name="folder" id="view_folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <input type="submit" name="view_images" value="View Images">
    </form>

    <!-- HTML form for "MITM Example" -->
    <h1>MITM Example</h1>
    <form action="index.php" method="GET">
        <label for="example_folder">Select a folder for example:</label>
        <select name="folder" id="example_folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <input type="submit" name="one_example" value="View">
    </form>

    <!-- Feedback area for displaying messages -->
    <div id="upload-feedback">
        <?php
        if (isset($_GET['message'])) {
            echo '<p>' . htmlspecialchars($_GET['message']) . '</p>';
        }
        ?>
    </div>
</body>
</html>
