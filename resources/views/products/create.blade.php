@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Add New Product</h2>
                <a href="{{ route('products.index') }}" class="text-blue-600 hover:text-blue-800">← Back to Products</a>
            </div>

            <form id="productForm" enctype="multipart/form-data">
                @csrf
                
                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Product Name *</label>
                        <input type="text" name="name" id="name" required 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter product name">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="4" 
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Enter product description (optional - AI will generate if left empty)"></textarea>
                        <p class="mt-1 text-sm text-gray-500">Leave empty to generate description using AI</p>
                    </div>

                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700">Product Image</label>
                        <input type="file" name="image" id="image" accept="image/*" 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">Leave empty to generate image using AI</p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">AI-Powered Features</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li>Automatically generate product descriptions using AI</li>
                                        <li>Create product images using AI image generation</li>
                                        <li>Smart field completion for missing information</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('products.index') }}" 
                           class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancel
                        </a>
                        <button type="submit" id="submitBtn"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Create Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@stack('scripts')
<script>
document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;
    
    submitBtn.textContent = 'Creating...';
    submitBtn.disabled = true;
    
    axios.post('{{ route("products.store") }}', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    })
    .then(function (response) {
        if (response.data.success) {
            alert('Product created successfully!');
            window.location.href = '{{ route("products.index") }}';
        } else {
            alert('Error: ' + response.data.message);
        }
    })
    .catch(function (error) {
        let errorMessage = 'Failed to create product';
        if (error.response && error.response.data) {
            if (error.response.data.errors) {
                const errors = Object.values(error.response.data.errors).flat();
                errorMessage = 'Validation errors:\n' + errors.join('\n');
            } else if (error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        }
        alert(errorMessage);
    })
    .finally(function () {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});
</script>
@endsection
