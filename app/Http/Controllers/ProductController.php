<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Size;
use App\Models\Color;
use App\Models\ProductDetail;
use App\Models\ProductImage;





class ProductController extends Controller
{
    public function index()
{
    // Lấy danh sách sản phẩm kèm theo chi tiết và hình ảnh, mỗi trang hiển thị 10 mục
    $dsProducts = Product::with('productDetails.images')->paginate(10);

    // Trả về view cùng dữ liệu
    return view('product.index', compact('dsProducts'));
}



    public function themMoi()
{
    $dsLoaiSP = Category::all();
    // $dsNhaCungCap = Suppliers::all();
    $dsSize = Size::all();
    $dsMauSac = Color::all();
    $product = new Product();
    return view('product.add', compact('dsLoaiSP', 'dsSize', 'dsMauSac', 'product'));
}
public function capNhat($id)
{
    $product = Product::findOrFail($id);
    $categories = Category::all();
    // $suppliers = Suppliers::all();
    $sizes = Size::all();
    $colors = Color::all();
    $productDetail = ProductDetail::where('product_id', $id)->first();

    return view('product.update', compact('product', 'categories', 'sizes', 'colors', 'productDetail'));
}

public function xuLyCapNhat(Request $request, $id)
{
    // Xác thực dữ liệu đầu vào
    $request->validate([
        'category_id' => 'required|integer|exists:categories,id',
        'name' => 'required|string|max:255',
        'price' => 'required|numeric',
        'description' => 'nullable|string',
        'size_id' => 'required|integer|exists:sizes,id',
        'color_id' => 'required|integer|exists:colors,id',
        'quality' => 'required|numeric|min:1',  // Thêm xác thực cho số lượng
        'images' => 'nullable|array',
        'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    // Tìm sản phẩm theo id
    $product = Product::findOrFail($id);

    // Cập nhật thông tin sản phẩm
    $product->update([
        'category_id' => $request->category_id,
        'name' => $request->name,
        'price' => $request->price,
        'description' => $request->description,
    ]);

    // Cập nhật hoặc tạo mới chi tiết sản phẩm
    $productDetail = ProductDetail::updateOrCreate(
        ['product_id' => $product->id, 'size_id' => $request->size_id, 'color_id' => $request->color_id],
        [
            'quality' => $request->quality,  // Cập nhật số lượng
            'description' => $request->description ?? '', // Cập nhật mô tả
            'status' => $request->status ?? 0, // Trạng thái mặc định là 0 nếu không có
        ]
    );

    // Xử lý hình ảnh (nếu có)
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $imageName = $image->getClientOriginalName();
            $image->storeAs('public/img/add', $imageName);
            $imageUrl = asset('storage/img/add/' . $imageName);

            // Lưu URL của ảnh vào bảng images (sản phẩm chi tiết)
            ProductImage::create([
                'product_detail_id' => $productDetail->id,
                'url' => $imageUrl,
            ]);
        }
    }

    // Trả về thông báo thành công
    return redirect()->route('product.index')->with('success', "Cập nhật sản phẩm thành công");
}


public function xuLyXoa($id)
{
    // Tìm sản phẩm theo ID
    $product = Product::find($id);

    // Kiểm tra nếu sản phẩm không tồn tại
    if (!$product) {
        return redirect()->route('product.index')->with('error', 'Sản phẩm không tồn tại');
    }

    // Xóa các hình ảnh liên quan đến sản phẩm
    $productImages = ProductImage::where('product_detail_id', $id)->get(); // Sửa lại dựa trên khóa ngoại
    foreach ($productImages as $image) {
        // Xóa file hình ảnh trên server
        $imagePath = public_path('storage/img/add/' . $image->url);
        if (file_exists($imagePath)) {
            unlink($imagePath); // Xóa tệp hình ảnh thực tế
        }
        // Xóa bản ghi hình ảnh trong cơ sở dữ liệu
        $image->delete();
    }

    // Xóa chi tiết sản phẩm (ProductDetail), bao gồm màu sắc và kích thước
    $productDetails = ProductDetail::where('product_id', $id)->get(); // Lấy chi tiết sản phẩm
    foreach ($productDetails as $detail) {
        // Xóa các hình ảnh liên quan đến chi tiết sản phẩm này
        $productDetailImages = ProductImage::where('product_detail_id', $detail->id)->get();
        foreach ($productDetailImages as $image) {
            $imagePath = public_path('storage/img/add/' . $image->url);
            if (file_exists($imagePath)) {
                unlink($imagePath); // Xóa tệp hình ảnh thực tế
            }
            $image->delete(); // Xóa bản ghi hình ảnh trong cơ sở dữ liệu
        }
        // Xóa chi tiết sản phẩm
        $detail->delete();
    }

    // Xóa sản phẩm chính
    $product->delete();

    return redirect()->route('product.index')->with('success', 'Sản phẩm đã được xóa thành công');
}





public function chiTiet($id)
{
    $product = Product::findOrFail($id);

    // Lấy loại sản phẩm
    $category = $product->category;

    // Lấy danh sách chi tiết sản phẩm (sắp xếp theo size_id)
    $dsChiTietSP = ProductDetail::where('product_id', $id)
        ->orderBy('size_id')
        ->get();

    // Tính tổng số lượng sản phẩm (sử dụng 'quantity' thay vì 'quality')
    $tongSoLuong = $dsChiTietSP->sum('quality');  // Thay 'quality' thành 'quantity'

    // Trả về view 'product.detail' với các biến compact
    return view('product.detail', compact('product', 'category', 'dsChiTietSP', 'tongSoLuong'));
}




  
public function xuLyThemMoi(Request $request)
{
    // Debug thông tin

    // Validate dữ liệu
    $request->validate([
        'category_id' => 'required|exists:categories,id',
        'name' => 'required|string|max:255',
        'price' => 'required|numeric',
        'description' => 'nullable|string|max:255',
        'size_id' => 'required|exists:sizes,id',
        'color_id' => 'required|exists:colors,id',
        'images' => 'nullable|array',
        'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    // Tạo mới sản phẩm
    $product = new Product();
    $product->category_id = $request->category_id;
    $product->name = $request->name;
    $product->price = $request->price;
    $product->description = $request->description;
    $product->save();

    // Tạo mới chi tiết sản phẩm
    $productDetail = new ProductDetail();
    $productDetail->product_id = $product->id;
    $productDetail->color_id = $request->color_id;
    $productDetail->size_id = $request->size_id;
    $productDetail->quality = $request->quality ?? 1; // Nếu không có quality, thì mặc định là 1
    $productDetail->status = 0; // Trạng thái mặc định là 0
    $productDetail->save();

    // Lưu hình ảnh
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $imageName = $image->getClientOriginalName();
            $image->storeAs('public/img/add', $imageName);
            ProductImage::create([
                'product_detail_id' => $productDetail->id,
                'url' => $imageName,
            ]);
        }
    }

    return redirect()->route('product.index')->with('themMoi', 'Thêm mới sản phẩm thành công');
}



public function timKiem(Request $request)
    {
        $keyword = $request->input('keyword');

        if (!empty($keyword)) {
            $dsProducts = Product::where('name', 'LIKE', '%' . $keyword . '%')
                ->orWhere('id', $keyword)
                ->paginate(20);
        } else {
            $dsProducts = Product::paginate(20);
        }

        // Tính tổng số lượng của tất cả chi tiết sản phẩm và trạng thái hàng
        foreach ($dsProducts as $product) {
            $product->total_quantity = $product->productDetails->sum('quantity_detail');
            $product->status = ($product->total_quantity > 0) ? 'Còn hàng' : 'Hết hàng';
        }

        return view('product.index', compact('dsProducts'));
    }

}