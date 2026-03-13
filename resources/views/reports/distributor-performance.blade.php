@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Distributor</th>
      <th>Jumlah Transaksi</th>
      <th>Total Penjualan (Rp)</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($distributors as $i => $row)
      <tr>
        <td align="center">{{ $i + 1 }}</td>
        <td>{{ $row->name }}</td>
        <td align="center">{{ $row->transaction_count }}</td>
        <td align="right">{{ number_format($row->total_sales, 0, ',', '.') }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="4" align="center">Tidak ada data</td>
      </tr>
    @endforelse
  </tbody>
  @if ($distributors->count())
    <tfoot>
      <tr>
        <th colspan="2">Total</th>
        <th align="center">{{ $distributors->sum('transaction_count') }}</th>
        <th align="right">{{ number_format($distributors->sum('total_sales'), 0, ',', '.') }}</th>
      </tr>
    </tfoot>
  @endif
</table>
@endsection
