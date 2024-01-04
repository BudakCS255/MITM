<!DOCTYPE html>
<html>
<head>
    <title>Image Upload and Viewer</title>
    <style>
        /* CSS Resets and Basic Styles */
        body {
            font-family: 'Arial', sans-serif;
            font-size: 16px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Responsive Layout */
        @media (max-width: 768px) {
            /* Adjust styles for smaller screens */
        }

        /* Styling Headings, Forms, and Buttons */
        h1, form {
            margin-bottom: 20px;
        }

        hr {
            background-color: black;
            height: 2px;
            border: none;
            margin: 20px 0; /* adds space above and below the line */
        }

        form {
            border: 2px solid black;
            padding: 15px;
            margin-bottom: 20px;
        }

        input[type="submit"], button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #45a049;
        }

        /* Image Styles */
        img {
            max-width: 100%;
            max-height: 400px;
            object-fit: cover;
            display: block;
            margin: 20px auto;
            padding-bottom: 20px;
            border-bottom: 2px solid black;
        }
    </style>
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
    <input type="submit" name="download_button" value="Download Images" class="download-link" id="download_zip" />
</form>

    <!-- HTML form for MITM Example -->
    <h1>MITM Example</h1>
    <form action="index.php" method="GET">
        <label for="one_example_folder">Select a folder to view encrypted images:</label>
        <select name="folder" id="one_example_folder">
            <option value="Case001">Case001</option>
            <option value="Case002">Case002</option>
            <option value="Case003">Case003</option>
        </select>
        <input type="submit" name="one_example" value="View Encrypted Images">
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

// Database configuration (can be moved to a separate config file)
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

// Check if the download GET parameter is set
if (isset($_GET['download_button']) && isset($_GET['folder'])) {
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

// Check if the server request method is POST for file upload
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Check if files were uploaded
    if (isset($_FILES["image"])) {
        $uploadedFiles = $_FILES["image"];
        $folder = sanitize_folder($_POST["folder"]); // Sanitize the folder input

        // Loop through the uploaded files
        foreach ($uploadedFiles["error"] as $key => $error) {
            // Check for file upload errors
            if ($error == UPLOAD_ERR_OK) {
                // Get the image data
                $imageData = file_get_contents($uploadedFiles["tmp_name"][$key]);

                // Encrypt the image data
                $encryptedImageData = xor_encrypt_decrypt($imageData, $encryptionKey);

                // Prepare and execute the database insertion
                $stmt = $conn->prepare("INSERT INTO $folder (images) VALUES (?)");
                $null = NULL; // This is needed to bind the blob data
                $stmt->bind_param("b", $null);
                $stmt->send_long_data(0, $encryptedImageData);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $message = "Image uploaded successfully!";
                } else {
                    $message = "Failed to upload the image.";
                }

                // Close the statement
                $stmt->close();
            } else {
                $message = "File upload error: " . $error;
            }
        }
    } else {
        $message = "No images were uploaded.";
    }

    // Close the database connection
    $conn->close();

    // Redirect back to the index.php with a message
    header("Location: index.php?message=" . urlencode($message));
    exit();
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

// Check if the server request method is GET and one_example is set
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['one_example'])) {
    // Sanitize the folder input
    $selectedFolder = sanitize_folder($_GET['folder']);

    // Query to retrieve encrypted image data from the selected folder table
    $sql = "SELECT id, images FROM $selectedFolder";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Output the images without decrypting
        while ($row = $result->fetch_assoc()) {
            $imageId = $row["id"];
            $encryptedImageData = $row["images"];

            $base64Image = base64_encode($encryptedImageData);
            echo "<div class='image-item'>";
            echo "<h2>Image $imageId</h2>";
            echo "<img src='data:image/jpeg;base64,$base64Image' alt='Encrypted Image $imageId'>";
            echo "</div>";
        }
    } else {
        echo "No images found in $selectedFolder.";
    }
}

// Close the database connection
$conn->close();
?>
