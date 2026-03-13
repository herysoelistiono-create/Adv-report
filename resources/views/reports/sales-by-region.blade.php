@extends('report.layout', ['title' => $title])

@section('content')
<table>
  <thead>
    <tr>
      <th>Provinsi</th>
      <th>Total Penjualan (Rp)</th>
      <th>Jumlah Transaksi</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($summary as $row)
      <tr>
        <td>{{ $row->province_name }}</td>
        <td align="right">{{ number_format($row->total_sales, 0, ',', '.') }}</td>
        <td align="center">{{ $row->transaction_count }}</td>
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
        <th align="right">{{ number_format($summary->sum('total_sales'), 0, ',', '.') }}</th>
        <th align="center">{{ $summary->sum('transaction_count') }}</th>
      </tr>
    </tfoot>
  @endif
</table>

@if (count($items))
  <h4 style="margin-top:20px;">Detail Transaksi</h4>
  <table>
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Provinsi</th>
        <th>Distributor</th>
        <th>Retailer</th>
        <th>Total (Rp)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($items as $item)
        <tr>
          <td>{{ $item->date }}</td>
          <td>{{ $item->province->name ?? '-' }}</td>
          <td>{{ $item->distributor->name ?? '-' }}</td>
          <td>{{ $item->retailer->name ?? '-' }}</td>
          <td align="right">{{ number_format($item->total_amount, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif
@endsection
