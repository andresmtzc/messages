# Use official PHP CLI image
FROM php:8.2-cli

# Set working directory inside container
WORKDIR /app

# Copy your project files into container
COPY . /app

# Expose port 10000 for the PHP built-in server
EXPOSE 10000

# Start PHP built-in server on all interfaces, port 10000, serving current directory
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
