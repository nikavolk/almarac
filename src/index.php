<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="fileManager()">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-6">File Manager</h1>
            
            <!-- Upload Form -->
            <form @submit.prevent="uploadFile" class="mb-8">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="file">
                        Select File (Max 5MB)
                    </label>
                    <input 
                        type="file" 
                        id="file" 
                        @change="handleFileSelect"
                        class="w-full px-3 py-2 border rounded-lg"
                        accept="*/*"
                    >
                </div>
                
                <!-- Progress Bar -->
                <div x-show="uploading" class="mb-4">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-blue-600 h-2.5 rounded-full" :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-1" x-text="`${progress}% uploaded`"></p>
                </div>

                <button 
                    type="submit" 
                    class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600"
                    :disabled="!selectedFile || uploading"
                >
                    Upload File
                </button>
            </form>

            <!-- File List -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Uploaded Files</h2>
                <div class="space-y-2">
                    <template x-for="file in files" :key="file.id">
                        <div class="flex items-center justify-between py-2 px-4 bg-gray-50 rounded-lg">
                            <a 
                                :href="'/download.php?file=' + encodeURIComponent(file.name)"
                                class="text-blue-600 hover:text-blue-800"
                                x-text="file.name"
                            ></a>
                            <button
                                @click="deleteFile(file.id)"
                                class="text-red-600 hover:text-red-800"
                            >
                                Delete
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fileManager() {
            return {
                files: [],
                selectedFile: null,
                uploading: false,
                progress: 0,

                async init() {
                    await this.loadFiles();
                },

                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file && file.size <= 5 * 1024 * 1024) { // 5MB limit
                        this.selectedFile = file;
                    } else {
                        alert('File size must be less than 5MB');
                        event.target.value = '';
                    }
                },

                async loadFiles() {
                    try {
                        const response = await fetch('/api/files.php');
                        const data = await response.json();
                        this.files = data;
                    } catch (error) {
                        console.error('Error loading files:', error);
                    }
                },

                async uploadFile() {
                    if (!this.selectedFile) return;

                    const formData = new FormData();
                    formData.append('file', this.selectedFile);

                    this.uploading = true;
                    this.progress = 0;

                    try {
                        const response = await fetch('/api/upload.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (response.ok) {
                            await this.loadFiles();
                            this.selectedFile = null;
                            document.getElementById('file').value = '';
                        } else {
                            throw new Error('Upload failed');
                        }
                    } catch (error) {
                        console.error('Error uploading file:', error);
                        alert('Failed to upload file');
                    } finally {
                        this.uploading = false;
                        this.progress = 0;
                    }
                },

                async deleteFile(fileId) {
                    if (!confirm('Are you sure you want to delete this file?')) return;

                    try {
                        const response = await fetch(`/api/delete.php?id=${fileId}`, {
                            method: 'DELETE'
                        });

                        if (response.ok) {
                            await this.loadFiles();
                        } else {
                            throw new Error('Delete failed');
                        }
                    } catch (error) {
                        console.error('Error deleting file:', error);
                        alert('Failed to delete file');
                    }
                }
            }
        }
    </script>
</body>
</html> 