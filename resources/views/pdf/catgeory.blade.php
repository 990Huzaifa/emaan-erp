<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Category Data List</title>
</head>
<body>
    <table>
        <tr>
            <th>No.</th>
            <th>Category Name</th>
            <th>Sub-Category Name</th>
        </tr>
        @foreach($data as $item)
        <tr>
            <td>{{$loop->index}}</td>
            <td>{{$item->product_category}}</td>
            <td>{{$item->name}}</td>
        </tr>
        @endforeach
    </table>
</body>
</html>