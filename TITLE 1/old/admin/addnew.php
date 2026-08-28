<?php
// Include the database connection file
require_once("connection.php"); // Ensure this includes the correct PDO connection

// Function to add a news item (title and description) and return the inserted ID
function addNewsItem($title, $description) {
    global $pdo;

    // Prepare the SQL statement to insert both title and description
    $stmt = $pdo->prepare("INSERT INTO news (title, description) VALUES (:title, :description)");

    // Bind parameters
    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);

    // Execute the statement
    if ($stmt->execute()) {
        // Return the ID of the newly inserted news item
        return $pdo->lastInsertId();
    } else {
        echo "Error: " . $stmt->errorInfo()[2];
        return false;
    }
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the form inputs
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Add the news item and get its ID
    $newsId = addNewsItem($title, $description);

    // Check if the news item was added successfully
    if ($newsId) {
        // Handle file upload for the image
        if (isset($_FILES['img']) && $_FILES['img']['error'] == 0) {
            // Define the directory for the specific news item (../assets/img/news/{id}/)
            $upload_dir = "../assets/img/news/$newsId/";

            // Create the directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Define the file path within the news ID folder
            $image_path = $upload_dir . basename($_FILES['img']['name']);

            // Move the uploaded file to the server
            if (move_uploaded_file($_FILES['img']['tmp_name'], $image_path)) {
                // Successfully uploaded the image
                $message = "News item and image uploaded successfully!";
            } else {
                $message = "Error uploading image.";
            }
        } else {
            $message = "News item added successfully!";
        }
    } else {
        // Error message if insertion fails
        $message = "Error adding news item.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add News Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .alert {
            margin-top: 20px;
            text-align: center;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

    <!-- Display Success or Error Message -->
    <?php if (isset($message)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <!-- Redirect after 3 seconds -->
        <script>
            setTimeout(function() {
                window.location.href = "news.php"; // Redirect to news.php
            }, 3000); // 3 seconds delay
        </script>
    <?php endif; ?>

    <!-- Button to trigger modal (Optional) -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal">
        Add News Item
    </button>

    <!-- Modal -->
    <div class="modal fade" id="addNewsModal" tabindex="-1" aria-labelledby="addNewsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addNewsModalLabel">Add News Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <!-- Title Input -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Title:</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>

                        <!-- Description Input -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Description:</label>
                            <textarea id="description" name="description" class="form-control" required></textarea>
                        </div>

                        <!-- Image Input -->
                        <div class="mb-3">
                            <label for="img" class="form-label">Image:</label>
                            <input type="file" id="img" name="img" class="form-control" accept="image/*">
                        </div>

                        <!-- Submit Button -->
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Add News Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
