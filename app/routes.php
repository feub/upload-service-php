<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello upload.');
        return $response;
    });

    /**
     * Upload endpoint
     *
     * By default, the uploaded file will be named that way: Facture-<random-hash>.<extension>
     * Along with the file, you may pass a "description" parameter that will replace the string "Facture".
     */
    $app->post('/upload', function (Request $request, Response $response) {
        $directory = __DIR__ . '/../upload';
        $authorized_extensions = ['pdf', 'jpg', 'png'];

        // Get all parsed body parameters as array
        $params = $request->getParsedBody();

        // Get the string value
        $description = $params['description'] ?? "Facture";

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Handle uploaded files
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['file'])) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'No file uploaded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $file = $uploadedFiles['file'];

        // Validate and move file
        if ($file->getError() === UPLOAD_ERR_OK) {
            // Get the file extension
            $fileExtension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

            // Check extension
            if (!in_array($fileExtension, $authorized_extensions)) {
                $response->getBody()->write(json_encode(['success' => false, 'error' => 'File extension not permitted. Must be: '.implode(', ',$authorized_extensions)]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(415); // Unsupported Media Type
            }

            // Get the file size
            $fileSize = $file->getSize();

            // Maximum file size
            $maxFileSize = 10 * 1024 * 1024 ;  // 10 MB

            // Check if the file size exceeds the limit
            if ($fileSize > $maxFileSize) {
                $response->getBody()->write(json_encode(['success' => false, 'error' => 'File size exceeds limit. Max: '.$maxFileSize]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Payload Too Large
            }

            $filename = moveUploadedFile($directory, $file, $description);
            $response->getBody()->write(json_encode(['success' => true, 'filename' => $filename]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $response->getBody()->write(json_encode(['success' => false, 'error' => 'Failed to upload file']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    });
};

// Helper function to move uploaded file
function moveUploadedFile($directory, $uploadedFile, $desc)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // Generate unique filename
    $filename = sprintf('%s-%s.%s', $desc, $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}
