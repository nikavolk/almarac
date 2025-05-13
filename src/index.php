<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
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
            color: white;
            font-size: 8px;
            width: 0%;
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

        <h1 class="text-3xl font-bold mb-6 text-center text-amber-600">File Manager</h1>

        <div x-show="errorMessage" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"
            x-text="errorMessage"></div>

        <div x-show="successMessage" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded"
            x-text="successMessage"></div>

        <section class="mb-8 p-6 border border-gray-200 rounded-md bg-gray-50">
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Upload New File</h2>
            <form @submit.prevent="uploadFileHandler" id="uploadForm" x-ref="uploadFormRef">

                <div>
                    <input type="file" id="fileInput" name="fileInput" @change="handleFileSelect($event)" class="hidden"
                        x-ref="fileInputRef" multiple>
                    <div x-data="{
                            dragging: false,
                            handleDrop(event) {
                                this.dragging = false;
                                if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                                    $refs.fileInputRef.files = event.dataTransfer.files;
                                    const changeEvent = new Event('change', { bubbles: true });
                                    $refs.fileInputRef.dispatchEvent(changeEvent);
                                }
                            }
                        }" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)" @click="$refs.fileInputRef.click()"
                        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md cursor-pointer"
                        :class="{ 'border-amber-500 bg-amber-50': dragging }">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" :class="{'text-amber-600': dragging}"
                                stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path
                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600" :class="{'text-amber-700': dragging}">
                                <p class="pl-1">Drag and drop a file here, or <span
                                        class="font-medium text-amber-600 hover:text-amber-500">click to upload</span>
                                </p>
                            </div>
                            <p class="text-xs text-gray-500" :class="{'text-amber-500': dragging}">Max file size: 5MB.
                                Allowed: JPG, PNG, PDF, DOCX, TXT, ZIP</p>
                        </div>
                    </div>
                </div>

                <div x-show="selectedFileNames.length > 0" class="mt-3 text-sm text-gray-700">
                    <p class="font-medium mb-1">Selected files (<span x-text="selectedFileNames.length"></span>):</p>
                    <ul class="list-disc list-inside max-h-32 overflow-y-auto">
                        <template x-for="(name, index) in selectedFileNames" :key="index">
                            <li x-text="name"></li>
                        </template>
                    </ul>
                    <button type="button" @click="clearSelectedFiles"
                        class="mt-1 text-sm text-red-600 hover:text-red-800">&times; Clear selection</button>
                </div>

                <div x-show="isUploading" class="mb-4 mt-4">
                    <div class="mb-1">
                        <span x-text="currentUploadStatus"></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-bar-inner" :style="{ width: overallUploadProgress + '%' }"></div>
                    </div>
                    <p class="text-sm text-center mt-1" x-text="overallUploadProgress + '%'"></p>
                </div>

                <button type="submit" :disabled="isUploading || selectedFiles.length === 0"
                    class="w-full mt-5 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="!isUploading">Upload Selected Files</span>
                    <span x-show="isUploading">Uploading...</span>
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
        </section>

        <section>
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
            </s>
    </div>

    <script>
        const API_BASE_URL = './api';

        function fileManager() {
            return {
                files: [],
                isLoadingList: true,
                isUploading: false,
                uploadProgress: null,
                isDeleting: null,
                errorMessage: '',
                successMessage: '',
                selectedFile: null,
                selectedFileName: '',
                selectedFiles: [],
                selectedFileNames: [],
                overallUploadProgress: 0,
                currentUploadStatus: '',

                handleFileSelect(event) {
                    const newSelectedFiles = [];
                    const newSelectedFileNames = [];
                    const files = event.target.files;

                    if (files && files.length > 0) {
                        for (let i = 0; i < files.length; i++) {
                            newSelectedFiles.push(files[i]);
                            newSelectedFileNames.push(files[i].name);
                        }
                    }

                    this.selectedFiles = newSelectedFiles;
                    this.selectedFileNames = newSelectedFileNames;

                    if (newSelectedFiles.length > 0) {
                        this.errorMessage = this.errorMessage.startsWith('Upload failed') || this.errorMessage.startsWith('File exceeds') || this.errorMessage.startsWith('Invalid file type') ? '' : this.errorMessage;
                    } else {
                        this.currentUploadStatus = '';
                        this.overallUploadProgress = 0;
                    }
                },

                clearSelectedFiles() {
                    this.$refs.fileInputRef.value = null;
                    this.selectedFiles = [];
                    this.selectedFileNames = [];
                    this.currentUploadStatus = '';
                    this.overallUploadProgress = 0;
                },

                showAlert(message, type = 'error', duration = 5000) {
                    if (type === 'error') {
                        this.errorMessage = message;
                    } else {
                        this.successMessage = message;
                    }
                    if (type === 'error') this.successMessage = ''; else this.errorMessage = '';

                    setTimeout(() => {
                        if (this.errorMessage === message) this.errorMessage = '';
                        if (this.successMessage === message) this.successMessage = '';
                    }, duration);
                },

                async fetchFiles() {
                    this.isLoadingList = true;
                    try {
                        const response = await fetch(`${API_BASE_URL}/files.php`);
                        if (!response.ok) {
                            let errorBody = `HTTP error! status: ${response.status}`;
                            try {
                                const errorJson = await response.json();
                                errorBody += `: ${errorJson.message || 'Unknown error'}`;
                            } catch (e) { }
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
                        this.files = [];
                    } finally {
                        this.isLoadingList = false;
                    }
                },

                async uploadFileHandler() {
                    if (this.selectedFiles.length === 0) {
                        this.showAlert('Please select one or more files to upload.');
                        return;
                    }

                    this.isUploading = true;
                    this.overallUploadProgress = 0;
                    this.errorMessage = '';
                    this.successMessage = '';
                    let successfulUploads = 0;
                    let failedUploads = 0;
                    let lastErrorMessage = '';

                    for (let i = 0; i < this.selectedFiles.length; i++) {
                        const fileToUpload = this.selectedFiles[i];
                        this.currentUploadStatus = `Uploading ${fileToUpload.name} (${i + 1} of ${this.selectedFiles.length})...`;

                        // client-side size validation
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        if (fileToUpload.size > maxSize) {
                            this.showAlert(`${fileToUpload.name} exceeds the 5MB limit. Skipping.`, 'error');
                            failedUploads++;
                            lastErrorMessage = `${fileToUpload.name} exceeds the 5MB limit.`;
                            this.overallUploadProgress = Math.round(((i + 1) / this.selectedFiles.length) * 100);
                            continue;
                        }

                        // client-side type validation
                        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/zip'];
                        const allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'txt', 'zip'];
                        let fileType = fileToUpload.type;
                        let fileExtension = fileToUpload.name.split('.').pop().toLowerCase();
                        let typeIsValid = allowedTypes.includes(fileType);
                        let extensionIsValid = allowedExtensions.includes(fileExtension);

                        if (!typeIsValid && fileType === 'application/octet-stream' && extensionIsValid) typeIsValid = true;
                        if (!typeIsValid && extensionIsValid && (fileType.startsWith('application/') || fileType.startsWith('image/') || fileType === 'text/plain')) {
                            console.warn(`Permissive type check: Allowing file ${fileToUpload.name} with MIME type ${fileType} due to valid extension ${fileExtension}.`);
                            typeIsValid = true;
                        }

                        if (!typeIsValid || !extensionIsValid) {
                            this.showAlert(`Invalid file type or extension for ${fileToUpload.name}. Skipping.`, 'error');
                            failedUploads++;
                            lastErrorMessage = `Invalid file type or extension for ${fileToUpload.name}.`;
                            this.overallUploadProgress = Math.round(((i + 1) / this.selectedFiles.length) * 100);
                            continue;
                        }

                        // upload individual file
                        try {
                            await this.uploadSingleFile(fileToUpload, (progress) => {
                                const baseProgress = (i / this.selectedFiles.length) * 100;
                                const currentFileProgressContribution = progress / this.selectedFiles.length;
                                this.overallUploadProgress = Math.round(baseProgress + currentFileProgressContribution);
                            });
                            successfulUploads++;
                        } catch (error) {
                            failedUploads++;
                            lastErrorMessage = error.message || `Upload failed for ${fileToUpload.name}.`;
                            this.showAlert(`Upload failed for ${fileToUpload.name}: ${lastErrorMessage}`, 'error');
                        }
                        this.overallUploadProgress = Math.round(((i + 1) / this.selectedFiles.length) * 100);
                    }

                    this.isUploading = false;
                    this.currentUploadStatus = 'Uploads complete.';

                    if (failedUploads > 0) {
                        if (this.selectedFiles.length === 1) {
                            this.showAlert(lastErrorMessage, 'error');
                        } else {
                            this.showAlert(`${failedUploads} file(s) failed to upload. Last error: ${lastErrorMessage}`, 'error');
                        }
                    } else if (successfulUploads > 0) {
                        this.showAlert(`${successfulUploads} file(s) uploaded successfully.`, 'success');
                    }

                    this.clearSelectedFiles();
                    this.fetchFiles();
                },

                uploadSingleFile(fileToUpload, onProgress) {
                    return new Promise((resolve, reject) => {
                        const formData = new FormData();
                        formData.append('uploadedFile', fileToUpload);
                        const xhr = new XMLHttpRequest();

                        xhr.upload.addEventListener('progress', (event) => {
                            if (event.lengthComputable) {
                                const percentComplete = Math.round((event.loaded / event.total) * 100);
                                if (onProgress) onProgress(percentComplete);
                            }
                        });

                        xhr.addEventListener('load', () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        resolve(response);
                                    } else {
                                        reject(new Error(response.message || 'Upload failed with non-success status.'));
                                    }
                                } catch (e) {
                                    console.error('Error parsing upload response:', e, xhr.responseText);
                                    reject(new Error(`Upload completed but failed to process response: ${e.message}.`));
                                }
                            } else {
                                console.error('Upload failed:', xhr.status, xhr.responseText);
                                let errorMsg = `Status ${xhr.status}.`;
                                try {
                                    const errorResponse = JSON.parse(xhr.responseText);
                                    errorMsg = errorResponse.message || errorMsg;
                                } catch (e) {
                                    const responseText = xhr.responseText || '(no response body)';
                                    errorMsg += ` Response: ${responseText.substring(0, 100)}${responseText.length > 100 ? '...' : ''}`;
                                }
                                reject(new Error(errorMsg));
                            }
                        });

                        xhr.addEventListener('error', () => {
                            console.error('Network error during upload.');
                            reject(new Error('Network error during upload. Please check your connection.'));
                        });

                        xhr.open('POST', `${API_BASE_URL}/upload.php`, true);
                        xhr.send(formData);
                    });
                },

                async deleteFile(fileId) {
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

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            const errorMsg = data.message || response.statusText || `HTTP error! status: ${response.status}`;
                            throw new Error(errorMsg);
                        }

                        this.showAlert(`"${fileName}" deleted successfully!`, 'success');
                        this.fetchFiles();

                    } catch (error) {
                        console.error('Delete file error:', error);
                        this.showAlert(`Error deleting file: ${error.message}`);
                    } finally {
                        this.isDeleting = null;
                    }
                },

                formatBytes(bytes, decimals = 2) {
                    if (!+bytes) return '0 Bytes'

                    const k = 1024
                    const dm = decimals < 0 ? 0 : decimals
                    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

                    const i = Math.floor(Math.log(bytes) / Math.log(k))

                    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
                },

                formatDate(dateString) {
                    try {
                        const date = new Date(dateString.replace(' ', 'T') + 'Z');
                        if (isNaN(date.getTime())) {
                            throw new Error('Invalid date value');
                        }
                        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' };
                        return date.toLocaleDateString(undefined, options);
                    } catch (e) {
                        console.error("Error formatting date:", dateString, e);
                        return dateString;
                    }
                }
            }
        }
    </script>

</body>

</html>