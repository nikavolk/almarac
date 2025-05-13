<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Simple progress bar style */
        .progress-bar {
            height: 10px;
            background-color: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            transition: width 0.3s ease-in-out;
        }

        .progress-bar-inner {
            height: 100%;
            background-color: #4caf50;
            border-radius: 5px;
            text-align: center;
            line-height: 10px;
            /* Adjust if needed */
            color: white;
            font-size: 8px;
            /* Adjust if needed */
            width: 0%;
            /* Initial width */
            transition: width 0.3s ease-in-out;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto mt-10 p-6 bg-white rounded-lg shadow-lg max-w-4xl" x-data="fileManager()"
        x-init="fetchFiles()" x-cloak>

        <h1 class="text-3xl font-bold mb-6 text-center text-indigo-600">File Manager</h1>

        <!-- Error Message -->
        <div x-show="errorMessage" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"
            x-text="errorMessage"></div>
        <!-- Success Message -->
        <div x-show="successMessage" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded"
            x-text="successMessage"></div>

        <!-- Upload Section -->
        <div class="mb-8 p-6 border border-gray-200 rounded-md bg-gray-50">
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Upload New File</h2>
            <form @submit.prevent="uploadFile" id="uploadForm">
                <div class="mb-4">
                    <label for="fileInput" class="block text-sm font-medium text-gray-700 mb-1">Select file (Max
                        5MB):</label>
                    <input type="file" id="fileInput" name="fileInput" required
                        class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Max file size: 5MB. Allowed types: jpg, png, pdf, docx, txt,
                        zip.</p>
                </div>

                <!-- Progress Bar -->
                <div x-show="uploadProgress !== null" class="mb-4">
                    <div class="progress-bar">
                        <div class="progress-bar-inner" :style="{ width: uploadProgress + '%' }"></div>
                    </div>
                    <p class="text-sm text-center mt-1" x-text="uploadProgress + '%'"></p>
                </div>

                <button type="submit" :disabled="isUploading"
                    class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isUploading">Upload File</span>
                    <span x-show="isUploading">Uploading...</span>
                    <!-- Simple spinner -->
                    <svg x-show="isUploading" class="animate-spin -mr-1 ml-3 h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                </button>
            </form>
        </div>

        <!-- File List Section -->
        <div>
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Uploaded Files</h2>
            <div x-show="isLoadingList" class="text-center text-gray-500">Loading files...</div>
            <div x-show="!isLoadingList && files.length === 0" class="text-center text-gray-500 py-4">No files uploaded
                yet.</div>

            <div x-show="!isLoadingList && files.length > 0" class="overflow-x-auto rounded-md border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Filename</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Size</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Upload Date</th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="file in files" :key="file.id">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                    x-text="file.original_filename"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                    x-text="formatBytes(file.file_size)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                    x-text="formatDate(file.uploaded_at)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                    <a :href="'download.php?id=' + file.id" target="_blank"
                                        class="text-indigo-600 hover:text-indigo-900" title="Download">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                    <button @click="deleteFile(file.id)" :disabled="isDeleting === file.id"
                                        class="text-red-600 hover:text-red-900 disabled:opacity-50 disabled:cursor-not-allowed"
                                        title="Delete">
                                        <svg x-show="isDeleting !== file.id" xmlns="http://www.w3.org/2000/svg"
                                            class="h-5 w-5 inline-block" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <!-- Simple spinner for delete -->
                                        <svg x-show="isDeleting === file.id"
                                            class="animate-spin h-5 w-5 text-red-600 inline-block"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API_BASE_URL = './api'; // Adjust if your API endpoints are elsewhere

        function fileManager() {
            return {
                files: [],
                isLoadingList: true,
                isUploading: false,
                uploadProgress: null, // null or 0-100
                isDeleting: null, // null or file_id being deleted
                errorMessage: '',
                successMessage: '',

                showAlert(message, type = 'error', duration = 5000) {
                    if (type === 'error') {
                        this.errorMessage = message;
                    } else {
                        this.successMessage = message;
                    }
                    // Clear previous messages immediately
                    if (type === 'error') this.successMessage = ''; else this.errorMessage = '';

                    setTimeout(() => {
                        if (this.errorMessage === message) this.errorMessage = '';
                        if (this.successMessage === message) this.successMessage = '';
                    }, duration);
                },

                async fetchFiles() {
                    this.isLoadingList = true;
                    // Don't clear errors immediately, allow user to see them
                    // this.errorMessage = '';
                    try {
                        const response = await fetch(`${API_BASE_URL}/files.php`);
                        if (!response.ok) {
                            // Try to parse error json from backend if available
                            let errorBody = `HTTP error! status: ${response.status}`;
                            try {
                                const errorJson = await response.json();
                                errorBody += `: ${errorJson.message || 'Unknown error'}`;
                            } catch (e) { /* Ignore if body isn't json */ }
                            throw new Error(errorBody);
                        }
                        const data = await response.json();
                        if (data.success) {
                            this.files = data.files;
                        } else {
                            throw new Error(data.message || 'Failed to fetch files.');
                        }
                    } catch (error) {
                        console.error('Fetch files error:', error);
                        this.showAlert(`Error fetching files: ${error.message}`);
                        this.files = []; // Clear files on error
                    } finally {
                        this.isLoadingList = false;
                    }
                },

                uploadFile() {
                    const fileInput = document.getElementById('fileInput');
                    const file = fileInput.files[0];
                    if (!file) {
                        this.showAlert('Please select a file to upload.');
                        return;
                    }

                    // Basic client-side validation (size)
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (file.size > maxSize) {
                        this.showAlert('File exceeds the 5MB limit.');
                        return;
                    }

                    // Basic client-side validation (type) - adjust as needed
                    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/zip'];
                    // A more robust check might involve checking extensions as well, as mime type can be unreliable
                    if (!allowedTypes.includes(file.type)) {
                        // Allow common extensions explicitly if mime type is generic (e.g., application/octet-stream)
                        const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'txt', 'zip'];
                        const fileExtension = file.name.split('.').pop().toLowerCase();
                        if (!allowedExtensions.includes(fileExtension)) {
                            this.showAlert('Invalid file type or extension. Allowed: jpg, png, pdf, docx, txt, zip.');
                            return;
                        }
                    }


                    this.isUploading = true;
                    this.uploadProgress = 0;
                    this.errorMessage = '';
                    this.successMessage = '';

                    const formData = new FormData();
                    formData.append('uploadedFile', file); // Match the name expected by upload.php

                    const xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', (event) => {
                        if (event.lengthComputable) {
                            const percentComplete = Math.round((event.loaded / event.total) * 100);
                            this.uploadProgress = percentComplete;
                        }
                    });

                    xhr.addEventListener('load', () => {
                        this.isUploading = false;
                        this.uploadProgress = null; // Reset progress bar
                        fileInput.value = ''; // Clear the file input

                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    this.showAlert('File uploaded successfully!', 'success');
                                    this.fetchFiles(); // Refresh the list
                                } else {
                                    throw new Error(response.message || 'Upload failed with non-success status.');
                                }
                            } catch (e) {
                                console.error('Error parsing upload response:', e, xhr.responseText);
                                this.showAlert(`Upload completed but failed to process response: ${e.message}. Response: ${xhr.responseText}`);
                            }
                        } else {
                            console.error('Upload failed:', xhr.status, xhr.responseText);
                            let errorMsg = `Upload failed with status ${xhr.status}.`;
                            try {
                                // Attempt to parse JSON error response from backend
                                const errorResponse = JSON.parse(xhr.responseText);
                                errorMsg = errorResponse.message || errorMsg; // Use backend message if available
                            } catch (e) {
                                // If response is not JSON, append the raw text (truncated)
                                const responseText = xhr.responseText || '(no response body)';
                                errorMsg += ` Response: ${responseText.substring(0, 100)}${responseText.length > 100 ? '...' : ''}`;
                            }
                            this.showAlert(errorMsg);
                        }
                    });

                    xhr.addEventListener('error', () => {
                        this.isUploading = false;
                        this.uploadProgress = null;
                        fileInput.value = ''; // Clear the file input
                        console.error('Network error during upload.');
                        this.showAlert('Network error during upload. Please check your connection.');
                    });

                    xhr.open('POST', `${API_BASE_URL}/upload.php`, true);
                    // Add headers if needed (e.g., for authentication in the future)
                    // xhr.setRequestHeader('Authorization', 'Bearer YOUR_TOKEN');
                    xhr.send(formData);
                },

                async deleteFile(fileId) {
                    // Use a more specific confirmation message
                    const fileToDelete = this.files.find(f => f.id === fileId);
                    const fileName = fileToDelete ? fileToDelete.original_filename : 'this file';
                    if (!confirm(`Are you sure you want to delete "${fileName}"?`)) {
                        return;
                    }

                    this.isDeleting = fileId;
                    this.errorMessage = '';
                    this.successMessage = '';

                    try {
                        const response = await fetch(`${API_BASE_URL}/delete.php`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ file_id: fileId })
                        });

                        const data = await response.json(); // Always try to parse JSON

                        if (!response.ok || !data.success) {
                            // Prefer message from JSON response, fallback to status text or generic error
                            const errorMsg = data.message || response.statusText || `HTTP error! status: ${response.status}`;
                            throw new Error(errorMsg);
                        }

                        this.showAlert(`"${fileName}" deleted successfully!`, 'success');
                        this.fetchFiles(); // Refresh list

                    } catch (error) {
                        console.error('Delete file error:', error);
                        this.showAlert(`Error deleting file: ${error.message}`);
                    } finally {
                        this.isDeleting = null;
                    }
                },

                // Helper function for formatting file size
                formatBytes(bytes, decimals = 2) {
                    if (!+bytes) return '0 Bytes'

                    const k = 1024
                    const dm = decimals < 0 ? 0 : decimals
                    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

                    const i = Math.floor(Math.log(bytes) / Math.log(k))

                    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
                },

                // Helper function for formatting date
                formatDate(dateString) {
                    try {
                        // Attempt to handle potential MySQL datetime format directly
                        const date = new Date(dateString.replace(' ', 'T') + 'Z'); // Assume UTC from DB
                        if (isNaN(date.getTime())) { // Check if date is valid
                            throw new Error('Invalid date value');
                        }
                        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' };
                        return date.toLocaleDateString(undefined, options);
                    } catch (e) {
                        console.error("Error formatting date:", dateString, e);
                        return dateString; // Return original if formatting fails
                    }
                }
            }
        }
    </script>

</body>

</html>