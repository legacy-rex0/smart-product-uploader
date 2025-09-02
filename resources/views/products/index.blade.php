@extends('layouts.app')

@section('content')
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 bg-white border-b border-gray-200">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Products</h2>
            <div class="flex space-x-3">
                <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Add Product
                </a>
                <button onclick="openBulkUploadModal()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Bulk Upload
                </button>
            </div>
        </div>
        
        <!-- Queue Worker Status -->
        <!-- <div id="queueStatus" class="mb-4 p-3 bg-gray-50 rounded-md border">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div id="queueStatusIndicator" class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <span id="queueStatusText" class="text-sm text-gray-600">Checking queue status...</span>
                </div>
                <button id="startQueueBtn" onclick="startQueueWorker()" class="hidden px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                    Start Queue Worker
                </button>
            </div>
        </div> -->

        @if($products->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">AI Generated</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($products as $product)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-16 w-16 object-cover rounded">
                                @else
                                    <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center">
                                        <span class="text-gray-500 text-xs">No Image</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('products.show', $product) }}" class="text-blue-600 hover:text-blue-800">
                                <div class="text-sm font-medium">{{ $product->name }}</div>
                            </a>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    {{ Str::limit($product->description, 100) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ $product->upload_method === 'manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ ucfirst($product->upload_method) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-2">
                                    @if($product->is_ai_generated_description)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                            Description
                                        </span>
                                    @endif
                                    @if($product->is_ai_generated_image)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                            Image
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $product->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="{{ route('products.show', $product) }}" 
                                       class="text-blue-600 hover:text-blue-900">View</a>
                                    <a href="{{ route('products.edit', $product) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <button onclick="deleteProduct({{ $product->id }}, '{{ $product->name }}')" 
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6">
                {{ $products->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No products</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by creating a new product.</p>
                <div class="mt-6">
                    <a href="{{ route('products.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Add Product
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="bulkUploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Bulk Upload Products</h3>
            
            <!-- Queue Status Indicator -->
            <!-- <div id="queueStatusIndicator" class="mb-4 p-3 bg-gray-50 rounded-md border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <div id="queueStatusDot" class="w-3 h-3 rounded-full bg-gray-400"></div>
                        <span id="queueStatusText" class="text-sm text-gray-600">Checking queue status...</span>
                    </div>
                    <button id="startQueueBtn" onclick="startQueueWorker()" class="hidden px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                        Start Queue Worker
                    </button>
                </div>
            </div> -->
            
            <form id="bulkUploadForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Excel File</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv,.txt" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="validateFile(this)">
                    <p class="text-xs text-gray-500 mt-1">Supported formats: .xlsx, .xls, .csv, .txt (Max: 10MB)</p>
                    <p class="text-xs text-gray-500 mt-1">Make sure your file has a header row with: product_name, description, image_url</p>
                    <div id="fileValidation" class="hidden mt-2 p-2 rounded text-sm"></div>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600">
                        <strong>Required columns:</strong> product_name<br>
                        <strong>Optional columns:</strong> description, image_url<br>
                        <strong>Note:</strong> Missing descriptions and images will be AI-generated automatically
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeBulkUploadModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" id="uploadSubmitBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                        Upload
                    </button>
                </div>
            </form>

            <!-- Progress Section -->
            <div id="uploadProgress" class="hidden mt-6">
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <h4 class="text-sm font-medium text-blue-800 mb-2">Upload Progress</h4>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                        <div id="progressBar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <p id="progressMessage" class="text-sm text-blue-700">Starting upload...</p>
                    <div id="progressResults" class="hidden mt-3 p-3 bg-white rounded border">
                        <h5 class="font-medium text-gray-900 mb-2">Upload Results</h5>
                        <div id="resultsContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@stack('scripts')
<script>
function openBulkUploadModal() {
    document.getElementById('bulkUploadModal').classList.remove('hidden');
}

function closeBulkUploadModal() {
    document.getElementById('bulkUploadModal').classList.add('hidden');
}

function checkQueueStatus() {
    const statusDot = document.getElementById('queueStatusDot');
    const statusText = document.getElementById('queueStatusText');
    const startQueueBtn = document.getElementById('startQueueBtn');
    
    // Check queue status via API
    axios.get('{{ route("products.queue-status") }}')
    .then(function (response) {
        if (response.data.success) {
            const data = response.data.data;
            
            if (data.queue_ready) {
                statusDot.className = 'w-3 h-3 rounded-full bg-green-500';
                statusText.textContent = 'Queue system ready';
                startQueueBtn.classList.add('hidden');
            } else {
                statusDot.className = 'w-3 h-3 rounded-full bg-yellow-500';
                statusText.textContent = `${data.pending_jobs} jobs pending`;
                startQueueBtn.classList.remove('hidden');
            }
        } else {
            statusDot.className = 'w-3 h-3 rounded-full bg-red-500';
            statusText.textContent = 'Queue status unknown';
            startQueueBtn.classList.remove('hidden');
        }
    })
    .catch(function (error) {
        console.error('Error checking queue status:', error);
        statusDot.className = 'w-3 h-3 rounded-full bg-red-500';
        statusText.textContent = 'Error checking queue status';
        startQueueBtn.classList.remove('hidden');
    });
}

function startQueueWorker() {
    const startQueueBtn = document.getElementById('startQueueBtn');
    const statusText = document.getElementById('queueStatusText');
    
    startQueueBtn.disabled = true;
    startQueueBtn.textContent = 'Starting...';
    statusText.textContent = 'Starting queue worker...';
    
    axios.post('{{ route("products.start-queue-worker") }}')
    .then(function (response) {
        if (response.data.success) {
            statusText.textContent = 'Queue worker started successfully';
            setTimeout(() => {
                checkQueueStatus();
            }, 2000);
        } else {
            statusText.textContent = 'Failed to start queue worker';
            startQueueBtn.disabled = false;
            startQueueBtn.textContent = 'Start Queue Worker';
        }
    })
    .catch(function (error) {
        console.error('Error starting queue worker:', error);
        statusText.textContent = 'Error starting queue worker';
        startQueueBtn.disabled = false;
        startQueueBtn.textContent = 'Start Queue Worker';
    });
}

function validateFile(input) {
    const fileValidation = document.getElementById('fileValidation');
    const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
    
    fileValidation.classList.add('hidden');
    fileValidation.textContent = '';
    fileValidation.className = 'hidden mt-2 p-2 rounded text-sm';

    const file = input.files[0];
    if (!file) {
        fileValidation.textContent = 'Please select a file to upload.';
        fileValidation.classList.remove('hidden');
        fileValidation.classList.add('bg-red-100', 'text-red-700', 'border', 'border-red-200');
        uploadSubmitBtn.disabled = true;
        return;
    }

    if (file.size > 10 * 1024 * 1024) { // 10MB
        fileValidation.textContent = 'File size exceeds 10MB limit.';
        fileValidation.classList.remove('hidden');
        fileValidation.classList.add('bg-red-100', 'text-red-700', 'border', 'border-red-200');
        uploadSubmitBtn.disabled = true;
        return;
    }

    const allowedTypes = ['.xlsx', '.xls', '.csv', '.txt'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(fileExtension)) {
        fileValidation.textContent = 'Unsupported file type. Please use .xlsx, .xls, .csv, or .txt.';
        fileValidation.classList.remove('hidden');
        fileValidation.classList.add('bg-red-100', 'text-red-700', 'border', 'border-red-200');
        uploadSubmitBtn.disabled = true;
        return;
    }

    // File is valid
    fileValidation.textContent = 'File is valid and ready for upload.';
    fileValidation.classList.remove('hidden');
    fileValidation.classList.add('bg-green-100', 'text-green-700', 'border', 'border-green-200');
    uploadSubmitBtn.disabled = false;
}

document.getElementById('bulkUploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Reset progress section
    document.getElementById('uploadProgress').classList.add('hidden');
    document.getElementById('progressResults').classList.add('hidden');
    
    submitBtn.textContent = 'Uploading...';
    submitBtn.disabled = true;
    
    // Show initial progress
    document.getElementById('uploadProgress').classList.remove('hidden');
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressMessage').textContent = 'Starting upload...';
    
    axios.post('{{ route("products.bulk-upload") }}', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        },
        timeout: 30000 // 30 second timeout
    })
    .then(function (response) {
        console.log('Upload response:', response.data);
        
        if (response.data.success) {
            // Update progress message
            document.getElementById('progressMessage').textContent = 'Upload started successfully. Processing...';
            document.getElementById('progressBar').style.width = '10%';
            
            if (response.data.data && response.data.data.job_id) {
                console.log('Job ID found:', response.data.data.job_id);
                // Add a small delay to ensure the job has time to start processing
                setTimeout(() => {
                    trackUploadProgress(response.data.data.job_id);
                }, 2000);
            } else {
                console.error('No job ID in response:', response.data);
                document.getElementById('progressMessage').textContent = 'Upload started but no job ID received. Please check the console for details.';
                
                // Show error in results section
                document.getElementById('progressResults').classList.remove('hidden');
                document.getElementById('resultsContent').innerHTML = `
                    <div class="space-y-2">
                        <p class="text-red-600"><strong>Error:</strong> No job ID received</p>
                        <p class="text-sm text-gray-600">The upload may still be processing. Please check the console for details.</p>
                    </div>
                `;
                
                // Add close modal button
                const closeBtn = document.createElement('button');
                closeBtn.textContent = 'Close Modal';
                closeBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
                closeBtn.onclick = () => closeBulkUploadModal();
                document.getElementById('resultsContent').appendChild(closeBtn);
            }
        } else {
            console.error('Upload failed:', response.data);
            document.getElementById('progressMessage').textContent = 'Upload failed: ' + response.data.message;
            
            // Show error in results section
            document.getElementById('progressResults').classList.remove('hidden');
            document.getElementById('resultsContent').innerHTML = `
                <div class="space-y-2">
                    <p class="text-red-600"><strong>Upload Failed:</strong> ${response.data.message}</p>
                    <p class="text-sm text-gray-600">Please check your file and try again.</p>
                </div>
            `;
            
            // Add close modal button
            const closeBtn = document.createElement('button');
            closeBtn.textContent = 'Close Modal';
            closeBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
            closeBtn.onclick = () => closeBulkUploadModal();
            document.getElementById('resultsContent').appendChild(closeBtn);
        }
    })
    .catch(function (error) {
        console.error('Upload error:', error);
        
        let errorMessage = 'Upload failed';
        if (error.response && error.response.data) {
            errorMessage += ': ' + error.response.data.message;
        } else if (error.code === 'ECONNABORTED') {
            errorMessage = 'Upload timed out. Please try again.';
        } else {
            errorMessage += ': ' + error.message;
        }
        
        document.getElementById('progressMessage').textContent = errorMessage;
        
        // Show error in results section
        document.getElementById('progressResults').classList.remove('hidden');
        document.getElementById('resultsContent').innerHTML = `
            <div class="space-y-2">
                <p class="text-red-600"><strong>Upload Error:</strong> ${errorMessage}</p>
                <p class="text-sm text-gray-600">Please check your connection and try again.</p>
            </div>
        `;
        
        // Add close modal button
        const closeBtn = document.createElement('button');
        closeBtn.textContent = 'Close Modal';
        closeBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
        closeBtn.onclick = () => closeBulkUploadModal();
        document.getElementById('resultsContent').appendChild(closeBtn);
    })
    .finally(function () {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});

function trackUploadProgress(jobId) {
    const progressBar = document.getElementById('progressBar');
    const progressMessage = document.getElementById('progressMessage');
    const progressResults = document.getElementById('progressResults');
    const resultsContent = document.getElementById('resultsContent');
    
    console.log('Starting progress tracking for job:', jobId);
    console.log('Progress tracking URL:', `/products/bulk-upload/${jobId}/progress`);
    
    let retryCount = 0;
    const maxRetries = 10;
    
    const checkProgress = () => {
        console.log('Checking progress for job:', jobId, 'attempt:', retryCount + 1);
        
        axios.get(`/products/bulk-upload/${jobId}/progress`)
        .then(function (response) {
            console.log('Progress response:', response.data);
            
            if (response.data.success) {
                const progress = response.data.data.progress;
                const results = response.data.data.results;
                
                console.log('Progress data:', progress);
                console.log('Results data:', results);
                
                // Update progress bar
                if (progress && progress.percentage !== undefined) {
                    progressBar.style.width = progress.percentage + '%';
                    progressMessage.textContent = progress.message;
                }
                
                // Check if completed
                if (progress && progress.percentage >= 100) {
                    if (results) {
                        // Show results
                        progressResults.classList.remove('hidden');
                        resultsContent.innerHTML = `
                            <div class="space-y-2">
                                <p><strong>Status:</strong> ${results.status}</p>
                                <p><strong>Products Imported:</strong> ${results.success_count}</p>
                                <p><strong>Total Rows:</strong> ${results.total_rows}</p>
                                ${results.error_count > 0 ? `<p><strong>Errors:</strong> ${results.error_count}</p>` : ''}
                                ${results.errors && results.errors.length > 0 ? 
                                    `<div class="list-disc pl-5 text-sm text-red-600">${results.errors.map(e => `<li>${e}</li>`).join('')}</div>` : ''}
                            </div>
                        `;
                        
                        // Add reload button
                        const reloadBtn = document.createElement('button');
                        reloadBtn.textContent = 'Reload Page';
                        reloadBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700';
                        reloadBtn.onclick = () => window.location.reload();
                        resultsContent.appendChild(reloadBtn);
                        
                        // Add close modal button
                        const closeBtn = document.createElement('button');
                        closeBtn.textContent = 'Close Modal';
                        closeBtn.className = 'mt-3 ml-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
                        closeBtn.onclick = () => closeBulkUploadModal();
                        resultsContent.appendChild(closeBtn);
                        
                        // Don't auto-close modal - let user decide
                    } else {
                        // No results but job completed
                        progressMessage.textContent = 'Upload completed successfully!';
                        // Don't auto-close modal
                    }
                } else {
                    // Continue checking progress
                    setTimeout(checkProgress, 1500); // Check every 1.5 seconds
                }
            } else {
                console.error('Progress response not successful:', response.data);
                progressMessage.textContent = 'Progress tracking failed: ' + response.data.message;
                
                // Show error in results section instead of closing modal
                progressResults.classList.remove('hidden');
                resultsContent.innerHTML = `
                    <div class="space-y-2">
                        <p class="text-red-600"><strong>Error:</strong> ${response.data.message}</p>
                        <p class="text-sm text-gray-600">The upload may still be processing. You can check the status or close this modal.</p>
                    </div>
                `;
                
                // Add close modal button
                const closeBtn = document.createElement('button');
                closeBtn.textContent = 'Close Modal';
                closeBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
                closeBtn.onclick = () => closeBulkUploadModal();
                resultsContent.appendChild(closeBtn);
                
                // Retry after a delay
                if (retryCount < maxRetries) {
                    retryCount++;
                    setTimeout(checkProgress, 3000);
                } else {
                    progressMessage.textContent = 'Progress tracking failed after multiple attempts. Please check the console for details.';
                }
            }
        })
        .catch(function (error) {
            console.error('Progress tracking error:', error);
            retryCount++;
            
            let errorMessage = 'Error tracking progress';
            if (error.response && error.response.data) {
                errorMessage += ': ' + error.response.data.message;
                if (error.response.data.debug) {
                    console.log('Debug info:', error.response.data.debug);
                }
                
                // If job not found, retry after a delay (job might not have started yet)
                if (error.response.status === 404 && error.response.data.message.includes('Job not found')) {
                    console.log('Job not found, retrying in 3 seconds... (attempt', retryCount, 'of', maxRetries, ')');
                    if (retryCount < maxRetries) {
                        setTimeout(checkProgress, 3000);
                        return;
                    }
                }
            } else {
                errorMessage += ': ' + error.message;
            }
            
            progressMessage.textContent = errorMessage;
            
            // Show debug info in console for troubleshooting
            if (error.response && error.response.data && error.response.data.debug) {
                console.log('Progress tracking debug info:', error.response.data.debug);
            }
            
            // Retry if we haven't exceeded max retries
            if (retryCount < maxRetries) {
                setTimeout(checkProgress, 3000);
            } else {
                progressMessage.textContent = 'Progress tracking failed after multiple attempts. Please check the console for details.';
                
                // Show error in results section
                progressResults.classList.remove('hidden');
                resultsContent.innerHTML = `
                    <div class="space-y-2">
                        <p class="text-red-600"><strong>Error:</strong> Progress tracking failed after multiple attempts</p>
                        <p class="text-sm text-gray-600">Please check the browser console for detailed error information.</p>
                        <p class="text-sm text-gray-600">The upload may still be processing in the background.</p>
                    </div>
                `;
                
                // Add close modal button
                const manualReloadBtn = document.createElement('button');
                manualReloadBtn.textContent = 'Reload Page Manually';
                manualReloadBtn.className = 'mt-3 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700';
                manualReloadBtn.onclick = () => window.location.reload();
                resultsContent.appendChild(manualReloadBtn);
                
                // Add close modal button
                const closeBtn = document.createElement('button');
                closeBtn.textContent = 'Close Modal';
                closeBtn.className = 'mt-3 ml-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300';
                closeBtn.onclick = () => closeBulkUploadModal();
                resultsContent.appendChild(closeBtn);
            }
        });
    };
    
    // Start checking progress
    checkProgress();
}

function deleteProduct(productId, productName) {
    if (confirm(`Are you sure you want to delete '${productName}'? This action cannot be undone.`)) {
        const deleteBtn = event.target;
        const originalText = deleteBtn.textContent;
        
        deleteBtn.textContent = 'Deleting...';
        deleteBtn.disabled = true;
        
        axios.delete(`/products/${productId}`)
        .then(function (response) {
            if (response.data.success) {
                alert('Product deleted successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        })
        .catch(function (error) {
            let errorMessage = 'Failed to delete product';
            if (error.response && error.response.data) {
                errorMessage += ': ' + error.response.data.message;
            }
            alert(errorMessage);
        })
        .finally(function () {
            deleteBtn.textContent = originalText;
            deleteBtn.disabled = false;
        });
    }
}
</script>
@endsection
