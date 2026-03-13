@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>Produk</th>
      <th>Total Kuantitas</th>
      <th>Total Penjualan (Rp)</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($summary as $row)
      <tr>
        <td>{{ $row->product_name }}</td>
        <td align="right">{{ number_format($row->total_quantity, 2, ',', '.') }}</td>
        <td align="right">{{ number_format($row->total_sales, 0, ',', '.') }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="3" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
  @if ($summary->count())
    <tfoot>
      <tr>
        <th>Total</th>
        <th></th>
        <th align="right">{{ number_format($summary->sum('total_sales'), 0, ',', '.') }}</th>
      </tr>
    </tfoot>
  @endif
</table>
@endsection
