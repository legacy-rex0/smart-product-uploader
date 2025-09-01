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
            <form id="bulkUploadForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Excel File</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Supported formats: .xlsx, .xls, .csv (Max: 10MB)</p>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600">
                        <strong>Required columns:</strong> product_name (or name)<br>
                        <strong>Optional columns:</strong> description, image_url
                    </p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeBulkUploadModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                        Upload
                    </button>
                </div>
            </form>
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

document.getElementById('bulkUploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    submitBtn.textContent = 'Uploading...';
    submitBtn.disabled = true;
    
    axios.post('{{ route("products.bulk-upload") }}', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
    .then(function (response) {
        if (response.data.success) {
            alert(response.data.message);
            closeBulkUploadModal();
            window.location.reload();
        } else {
            alert('Upload failed: ' + response.data.message);
        }
    })
    .catch(function (error) {
        let errorMessage = 'Upload failed';
        if (error.response && error.response.data) {
            errorMessage += ': ' + error.response.data.message;
        }
        alert(errorMessage);
    })
    .finally(function () {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});

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
