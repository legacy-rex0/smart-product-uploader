# Smart Product Uploader

A Laravel-based system that allows for two methods of product uploading with AI-powered field completion and image generation.

## Features

### ✅ Core Features

1. **Manual Product Upload**
   - Basic web form for manual product input
   - Required: Product Name
   - Optional: Product Description, Product Image
   - AI-powered description generation when description is missing
   - AI-powered image generation when image is missing

2. **Bulk Product Upload**
   - Excel file upload (.xlsx, .xls, .csv)
   - Required: Product Name column
   - Optional: Description, Image URL columns
   - AI-powered completion for missing fields
   - Batch processing with progress tracking

3. **AI Integration**
   - OpenAI GPT-3.5 for description generation
   - DALL-E for image generation
   - Fallback to mock data when API keys are not configured
   - Configurable API endpoints

4. **File Storage**
   - Local storage with public access
   - Amazon S3 integration (when configured)
   - Automatic file organization
   - Public URL generation

### 🚀 Technical Features

- **Laravel 12** with modern PHP practices
- **TailwindCSS** for responsive UI
- **Excel import/export** with Maatwebsite Excel
- **Clean Architecture** with service classes
- **Database migrations** and models
- **RESTful API** endpoints
- **CSRF protection** and validation
- **Error handling** and logging

## Installation

### Prerequisites

- PHP 8.2+
- Composer
- SQLite (default) or MySQL/PostgreSQL

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd smart-product-uploader
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure environment variables**
   ```env
   # Database
   DB_CONNECTION=sqlite
   DB_DATABASE=/path/to/database.sqlite
   
   # OpenAI (Optional)
   OPENAI_API_KEY=your_openai_api_key_here
   OPENAI_BASE_URL=https://api.openai.com/v1
   
   # AWS S3 (Optional)
   AWS_ACCESS_KEY_ID=your_aws_access_key
   AWS_SECRET_ACCESS_KEY=your_aws_secret_key
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=your_s3_bucket_name
   AWS_ENDPOINTS=
   FILESYSTEM_DISK=s3
   ```

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan storage:link
   ```

6. **Start the application**
   ```bash
   php artisan serve
   ```

## Usage

### Manual Product Upload

1. Navigate to `/products/create`
2. Fill in the product name (required)
3. Optionally add description and image
4. Submit the form
5. AI will automatically generate missing fields

### Bulk Product Upload

1. Navigate to `/products` (main page)
2. Click "Bulk Upload" button
3. Upload Excel file with columns:
   - `product_name` or `name` (required)
   - `description` (optional)
   - `image_url` (optional)
4. System processes the file and generates missing data

### Excel File Format

The system supports Excel files with the following structure:

| product_name | description | image_url |
|--------------|-------------|-----------|
| Product A    | Description | URL       |
| Product B    |             |           |
| Product C    | Custom Desc |           |

- **product_name**: Required field
- **description**: Optional, AI-generated if empty
- **image_url**: Optional, AI-generated if empty

## API Endpoints

### Products

- `GET /products` - List all products
- `GET /products/create` - Show create form
- `POST /products` - Store new product
- `GET /products/{id}` - Show product details
- `POST /products/bulk-upload` - Bulk upload products

### Request/Response Format

#### Create Product
```json
POST /products
{
    "name": "Product Name",
    "description": "Optional description",
    "image": "image_file"
}

Response:
{
    "success": true,
    "message": "Product created successfully",
    "product": { ... }
}
```

#### Bulk Upload
```json
POST /products/bulk-upload
FormData: excel_file

Response:
{
    "success": true,
    "message": "Bulk upload completed. X products imported successfully.",
    "success_count": 10,
    "errors": []
}
```

## Configuration

### OpenAI Configuration

The system automatically uses OpenAI APIs when configured:

```env
OPENAI_API_KEY=your_api_key_here
OPENAI_BASE_URL=https://api.openai.com/v1
```

**Models Used:**
- **Description Generation**: GPT-3.5-turbo
- **Image Generation**: DALL-E 3

### S3 Configuration

Configure AWS S3 for cloud storage:

```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_ENDPOINTS=
FILESYSTEM_DISK=s3
```

### Fallback Behavior

When API keys are not configured:
- **Descriptions**: Generated from predefined templates
- **Images**: Generated from placeholder services
- **Storage**: Uses local storage


## Development

### Adding New Features

1. **Models**: Extend existing models or create new ones
2. **Services**: Add business logic in service classes
3. **Controllers**: Handle HTTP requests and responses
4. **Views**: Create Blade templates with TailwindCSS
5. **Migrations**: Update database schema as needed

### Testing

```bash
php artisan test
```


## Troubleshooting

### Common Issues

1. **Storage Link Not Working**
   ```bash
   php artisan storage:link
   ```

2. **Migration Errors**
   ```bash
   php artisan migrate:fresh
   ```

3. **File Upload Issues**
   - Check storage permissions
   - Verify disk configuration
   - Check file size limits

4. **AI Generation Not Working**
   - Verify OpenAI API key
   - Check API rate limits
   - Review error logs

### Logs

Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

## Support

For support and questions:
- Create an issue in the repository
- Check the documentation
- Review the code examples

---
